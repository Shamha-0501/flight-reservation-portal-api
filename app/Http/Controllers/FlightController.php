<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderAddon;
use App\Models\BookingAddon;
use App\Models\Passenger;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\CurrencyConverter;
use App\Jobs\SendBladeMail;
use Illuminate\Http\Request;
use App\Services\Duffel\DuffelService;
use App\Services\MailService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class FlightController extends Controller
{
    use FlightOfferFiltersTrait;

    protected DuffelService $duffel;
    protected CurrencyConverter $currencyConverter;
    protected ActivityLogger $activityLogger;

    public function __construct(
        DuffelService $duffel,
        CurrencyConverter $currencyConverter,
        ActivityLogger $activityLogger
    ) {
        $this->duffel = $duffel;
        $this->currencyConverter = $currencyConverter;
        $this->activityLogger = $activityLogger;
    }

    public function searchPlaces(Request $request)
    {
        try {
            $query = $request->query('q');

            if (!$query) {
                return response()->json(['error' => 'Query is required'], 422);
            }

            $result = $this->duffel->getPlaceSuggestions($query);

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Place search failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function searchFlights(Request $request)
    {
        try {
            $validated = $request->validate([
                'originLocationCode' => 'required|string|size:3',
                'destinationLocationCode' => 'required|string|size:3',
                'departureDate' => 'required|date_format:Y-m-d|after_or_equal:today',
                'returnDate' => 'nullable|date_format:Y-m-d|after_or_equal:departureDate',
                'trip' => 'nullable|in:oneway,roundtrip',

                'adults' => 'required|integer|min:1',
                'children' => 'nullable|integer|min:0',
                'infants' => 'nullable|integer|min:0',

                'childAges' => 'nullable|array',
                'childAges.*' => 'integer|min:2|max:11',

                'infantAges' => 'nullable|array',
                'infantAges.*' => 'integer|min:0|max:1',

                'travelClass' => 'nullable|string|in:ECONOMY,PREMIUM_ECONOMY,BUSINESS,FIRST',

                'outDepartMin' => 'nullable|integer|min:0|max:1439',
                'outDepartMax' => 'nullable|integer|min:0|max:1439',
                'outArriveMin' => 'nullable|integer|min:0|max:1439',
                'outArriveMax' => 'nullable|integer|min:0|max:1439',
                'inDepartMin' => 'nullable|integer|min:0|max:1439',
                'inDepartMax' => 'nullable|integer|min:0|max:1439',
                'inArriveMin' => 'nullable|integer|min:0|max:1439',
                'inArriveMax' => 'nullable|integer|min:0|max:1439',

                'stops' => 'nullable|array',
                'stops.*' => 'in:0,1,2',

                'supplierTimeout' => 'nullable|integer|min:1000|max:60000',
            ]);

            $search = $this->duffel->searchFlights($validated);
            $offerRequestId = $search['data']['id'] ?? null;

            if (!$offerRequestId) {
                return response()->json([
                    'error' => 'Failed to create offer request',
                    'response' => $search,
                ], 422);
            }

            return response()->json([
                'offer_request_id' => $offerRequestId,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Flight search failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function getOffers(Request $request)
    {
        try {
            $validated = $request->validate([
                'offer_request_id' => 'required|string',

                'minPrice' => 'nullable|numeric|min:0',
                'maxPrice' => 'nullable|numeric|min:0',

                'stops' => 'nullable|array',
                'stops.*' => 'in:0,1,2plus',

                'baggage' => 'nullable|array',
                'baggage.*' => 'in:carryOn,checked',

                'includeAirlines' => 'nullable|array',
                'includeAirlines.*' => 'string|max:3',
                'excludeAirlines' => 'nullable|array',
                'excludeAirlines.*' => 'string|max:3',

                'outDepartMin' => 'nullable|integer|min:0|max:1439',
                'outDepartMax' => 'nullable|integer|min:0|max:1439',
                'inDepartMin' => 'nullable|integer|min:0|max:1439',
                'inDepartMax' => 'nullable|integer|min:0|max:1439',

                'minDurationMinutes' => 'nullable|integer|min:0',
                'maxDurationMinutes' => 'nullable|integer|min:1',

                'avoidLayovers' => 'nullable|array',
                'avoidLayovers.*' => 'string|max:3',
                'onlyLayovers' => 'nullable|array',
                'onlyLayovers.*' => 'string|max:3',

                'refundable' => 'nullable|boolean',
                'changeable' => 'nullable|boolean',

                'minCheckedBags' => 'nullable|integer|min:0',

                'sortBy' => 'nullable|in:best,price,duration',
                'sortDir' => 'nullable|in:asc,desc',

                'limit' => 'nullable|integer|min:1|max:200',
            ]);

            $offersResponse = $this->duffel->getOffers(
                $validated['offer_request_id'],
                $validated['limit'] ?? 200
            );

            $allOffers = $this->safeArray($offersResponse['data'] ?? []);

            /**
             * Fast expiry check.
             * Do NOT call getOffer() for every offer here.
             * Duffel list offers usually already includes expires_at.
             */
            $offers = array_values(array_filter($allOffers, function (array $offer) {
                $expiresAt = $offer['expires_at'] ?? null;

                if (!$expiresAt) {
                    return true;
                }

                return Carbon::parse($expiresAt)->isFuture();
            }));

            $expiredRemoved = count($allOffers) - count($offers);

            $offers = $this->filterValidOffers($offers, 30);

            $normalizedAll = array_map(
                fn(array $offer) => $this->normalizeDuffelOffer($offer),
                $offers
            );

            $ranges = $this->buildRanges($normalizedAll);
            $facets = $this->buildFacets($normalizedAll);

            $filtered = array_values(array_filter(
                $normalizedAll,
                fn(array $offer) => $this->passesFilters($offer, $validated)
            ));

            $sortBy = $validated['sortBy'] ?? 'best';
            $sortDir = $validated['sortDir'] ?? 'asc';

            $filtered = $this->sortOffers($filtered, $sortBy, $sortDir);

            $summary = $this->buildSummaryCards($filtered);
            $appliedFilters = $this->buildAppliedFilters($validated, $ranges);

            return response()->json([
                'data' => $filtered,
                'meta' => [
                    'count' => count($filtered),
                    'total_received' => count($allOffers),
                    'expired_removed' => $expiredRemoved,
                    'valid_count' => count($offers),
                    'currency' => $this->defaultCurrency(),
                    'appliedFilters' => $appliedFilters,
                    'ranges' => $ranges,
                    'facets' => $facets,
                    'summary' => $summary,
                    'sort' => [
                        'current' => [
                            'sortBy' => $sortBy,
                            'sortDir' => $sortDir,
                        ],
                        'options' => [
                            ['sortBy' => 'best', 'label' => 'Best'],
                            ['sortBy' => 'price', 'label' => 'Cheapest'],
                            ['sortBy' => 'duration', 'label' => 'Fastest'],
                        ],
                    ],
                ],
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to fetch offers',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    public function getOffer(string $offerId)
    {
        try {
            $offer = $this->duffel->getOffer($offerId);

            $seatMaps = $this->duffel->getSeatMaps($offerId);

            $seatMapStatus = $this->determineSeatMapStatus($seatMaps);

            return response()->json([
                'offer' => $this->imposeDefaultCurrency($offer),
                'seat_map_status' => $seatMapStatus
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to fetch offer',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function createPaymentIntent(Request $request)
    {
        try {
            $validated = $request->validate([
                'offer_id' => 'required|string',
            ]);

            $offerResponse = $this->duffel->getOffer($validated['offer_id']);
            $offer = $offerResponse['data'] ?? null;

            if (!is_array($offer) || empty($offer)) {
                return response()->json([
                    'error' => 'Offer not found',
                ], 422);
            }

            $paymentIntent = $this->duffel->createPaymentIntent([
                'amount' => $offer['total_amount'],
                'currency' => $offer['total_currency'],
            ]);

            return response()->json($this->imposeDefaultCurrency($paymentIntent));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Payment intent creation failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function confirmPaymentIntent(string $paymentIntentId)
    {
        try {
            $paymentIntent = $this->duffel->confirmPaymentIntent($paymentIntentId);

            return response()->json($this->imposeDefaultCurrency($paymentIntent));
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Payment intent confirmation failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function createOrder(Request $request)
    {
        try {
            $validated = $request->validate([
                'tenantKey' => [
                    'required',
                    'string',
                    'exists:tenants,key',
                ],

                'offer_id' => [
                    'required',
                    'string',
                ],

                'passengers' => [
                    'required',
                    'array',
                    'min:1',
                ],

                'passengers.*.id' => [
                    'required',
                    'string',
                    'distinct',
                ],

                'passengers.*.type' => [
                    'required',
                    'string',
                    'in:adult,child,infant_without_seat',
                ],

                'passengers.*.title' => [
                    'required',
                    'string',
                ],

                'passengers.*.given_name' => [
                    'required',
                    'string',
                ],

                'passengers.*.family_name' => [
                    'required',
                    'string',
                ],

                'passengers.*.born_on' => [
                    'required',
                    'date_format:Y-m-d',
                ],

                'passengers.*.gender' => [
                    'required',
                    'string',
                ],

                'passengers.*.email' => [
                    'nullable',
                    'email',
                ],

                'passengers.*.phone_number' => [
                    'nullable',
                    'string',
                ],

                'passengers.*.loyalty_programme_accounts' => [
                    'nullable',
                    'array',
                ],

                /*
             * In your frontend, this value belongs to an
             * infant_without_seat passenger and contains the
             * Duffel passenger ID of the accompanying adult.
             */
                'passengers.*.infant_passenger_id' => [
                    'nullable',
                    'string',
                ],

                /*
             * Duffel seat services.
             *
             * Example:
             * [
             *     [
             *         'id' => 'ase_...',
             *         'quantity' => 1,
             *     ],
             * ]
             */
                'services' => [
                    'nullable',
                    'array',
                    'max:50',
                ],

                'services.*.id' => [
                    'required',
                    'string',
                    'distinct',
                    'regex:/^ase_/',
                ],

                'services.*.quantity' => [
                    'required',
                    'integer',
                    'in:1',
                ],

                // Existing custom order add-ons
                'addons' => [
                    'nullable',
                    'array',
                ],

                'booking_addons' => [
                    'nullable',
                    'array',
                ],

                'booking_addons.*.addon_id' => [
                    'required_with:booking_addons',
                    'integer',
                    'exists:addons,id',
                ],

                'booking_addons.*.addon_code' => [
                    'required_with:booking_addons',
                    'string',
                    'max:255',
                ],

                'booking_addons.*.addon_name' => [
                    'required_with:booking_addons',
                    'string',
                    'max:255',
                ],

                'booking_addons.*.price' => [
                    'required_with:booking_addons',
                    'numeric',
                    'min:0',
                ],

                'booking_addons.*.currency' => [
                    'required_with:booking_addons',
                    'string',
                    'size:3',
                ],

                'booking_addons.*.meta' => [
                    'nullable',
                    'array',
                ],

                'agency_markup' => [
                    'nullable',
                    'array',
                ],

                'agency_markup.enabled' => [
                    'required_with:agency_markup',
                    'boolean',
                ],

                'agency_markup.mode' => [
                    'required_with:agency_markup',
                    'in:percentage,fixed',
                ],

                'agency_markup.value' => [
                    'required_with:agency_markup',
                    'numeric',
                    'min:0',
                ],

                'agency_markup.amount' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],

                'agency_markup.currency' => [
                    'required_with:agency_markup',
                    'string',
                    'size:3',
                ],

                'agency_markup.label' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'contact_email' => [
                    'nullable',
                    'email',
                ],
            ]);

            /*
         * Resolve the tenant before calling Duffel.
         *
         * A database transaction cannot undo an external
         * Duffel order if the tenant validation later fails.
         */
            $tenant = Tenant::where('key', $validated['tenantKey'])
                ->firstOrFail();

            $authUser = $request->user();

            $orderEmail = $validated['contact_email']
                ?? collect($validated['passengers'])
                ->pluck('email')
                ->filter(
                    fn($email) =>
                    is_string($email) &&
                        trim($email) !== ''
                )
                ->first();

            $orderUser = $authUser;

            if (!$orderUser && $orderEmail) {
                $orderUser = User::where('email', $orderEmail)->first();
            }

            /*
         * Your current orders table and email flow expect a user.
         * Do not continue with a null user.
         */
            if (!$orderUser) {
                throw ValidationException::withMessages([
                    'contact_email' => [
                        'A registered or verified customer account is required before creating the booking.',
                    ],
                ]);
            }

            $recipientEmail = $orderEmail ?: $orderUser->email;

            if (!$recipientEmail) {
                throw ValidationException::withMessages([
                    'contact_email' => [
                        'A customer email address is required.',
                    ],
                ]);
            }

            /*
         * Retrieve the latest raw offer directly from Duffel.
         * Do not use a frontend copy of the offer price.
         */
            $offerResponse = $this->duffel->getOffer(
                $validated['offer_id']
            );

            $offer = $offerResponse['data'] ?? null;

            if (!is_array($offer) || empty($offer)) {
                throw ValidationException::withMessages([
                    'offer_id' => [
                        'The selected flight offer could not be found.',
                    ],
                ]);
            }

            $expiresAt = $offer['expires_at'] ?? null;

            if ($expiresAt && Carbon::parse($expiresAt)->isPast()) {
                throw ValidationException::withMessages([
                    'offer_id' => [
                        'The selected flight offer has expired. Please search again.',
                    ],
                ]);
            }

            /*
         * Move infant_passenger_id to the accompanying adult,
         * which is the structure expected by Duffel.
         */
            $duffelPassengers = $this->prepareDuffelPassengers(
                $validated['passengers']
            );

            /*
         * Refetch the raw seat map, validate the ase_ IDs,
         * match services to passengers and calculate the
         * complete Duffel payment amount.
         */
            $seatData = $this->prepareSelectedSeatServices(
                offerId: $validated['offer_id'],
                requestedServices: $validated['services'] ?? [],
                passengers: $validated['passengers'],
                offer: $offer
            );

            $payload = [
                'selected_offers' => [
                    $validated['offer_id'],
                ],

                'payments' => [
                    [
                        'type' => 'balance',
                        'amount' => $seatData['payment_amount'],
                        'currency' => $seatData['payment_currency'],
                    ],
                ],

                'passengers' => $duffelPassengers,
            ];

            if (!empty($seatData['duffel_services'])) {
                $payload['services'] = $seatData['duffel_services'];
            }

            /*
         * Prepare existing custom add-on values before the
         * external order is created.
         */
            $convertedOrderAddons = null;

            if (!empty($validated['addons'])) {
                $convertedOrderAddons =
                    $this->currencyConverter->convertPayload(
                        $validated['addons']
                    );
            }

            /*
         * Create the real Duffel order.
         */
            $duffelOrderResponse = $this->duffel->createOrder(
                $payload
            );

            $duffelOrder = $duffelOrderResponse['data']
                ?? $duffelOrderResponse;

            if (
                !is_array($duffelOrder) ||
                empty($duffelOrder['id'])
            ) {
                throw new \RuntimeException(
                    'Duffel returned an invalid order response.'
                );
            }

            /*
         * Read the confirmed seat assignments from the
         * created Duffel order.
         */
            $confirmedSeatAssignments =
                $this->extractConfirmedSeatAssignments(
                    $duffelOrder
                );

            /*
         * Only local database operations are placed inside
         * the database transaction.
         */
            $order = DB::transaction(function () use (
                $tenant,
                $orderUser,
                $recipientEmail,
                $offer,
                $duffelOrder,
                $validated,
                $seatData,
                $confirmedSeatAssignments,
                $convertedOrderAddons
            ) {
                $order = Order::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $orderUser->id,

                    'duffel_order_id' => $duffelOrder['id'],

                    'booking_reference' =>
                    $duffelOrder['booking_reference']
                        ?? null,

                    'type' => $duffelOrder['type']
                        ?? 'instant',

                    'status' => 'Booked',

                    'cancellation_status' =>
                    Order::CANCELLATION_STATUS_NONE,

                    'refund_status' => null,

                    /*
                 * Duffel's created order amounts should
                 * already include the selected seat services.
                 */
                    'base_amount' => $this->convertMoneyAmount(
                        $duffelOrder['base_amount']
                            ?? $offer['base_amount']
                            ?? null,
                        $duffelOrder['base_currency']
                            ?? $offer['base_currency']
                            ?? null
                    ),

                    'base_currency' => $this->defaultCurrency(),

                    'tax_amount' => $this->convertMoneyAmount(
                        $duffelOrder['tax_amount']
                            ?? $offer['tax_amount']
                            ?? null,
                        $duffelOrder['tax_currency']
                            ?? $offer['tax_currency']
                            ?? null
                    ),

                    'tax_currency' => $this->defaultCurrency(),

                    'total_amount' => $this->convertMoneyAmount(
                        $duffelOrder['total_amount']
                            ?? $seatData['payment_amount'],
                        $duffelOrder['total_currency']
                            ?? $seatData['payment_currency']
                    ),

                    'total_currency' => $this->defaultCurrency(),

                    'synced_at' => now(),

                    'void_window_ends_at' =>
                    $duffelOrder['void_window_ends_at']
                        ?? null,

                    'meta' => [
                        'offer' => $offer,

                        'duffel_order' => $duffelOrder,

                        'contact_email' => $recipientEmail,

                        /*
                     * Seat information derived from the raw
                     * pre-booking seat map.
                     */
                        'seat_selections' =>
                        $seatData['selections'],

                        /*
                     * Seat information returned after Duffel
                     * successfully created the order.
                     */
                        'confirmed_seat_assignments' =>
                        $confirmedSeatAssignments,

                        'provider_payment' => [
                            'offer_amount' =>
                            $seatData['offer_amount'],

                            'seat_services_amount' =>
                            $seatData['service_total_amount'],

                            'total_amount' =>
                            $seatData['payment_amount'],

                            'currency' =>
                            $seatData['payment_currency'],
                        ],

                        'agency_markup' =>
                        $validated['agency_markup']
                            ?? null,
                    ],
                ]);

                foreach (
                    $validated['passengers']
                    as $passengerData
                ) {
                    Passenger::create([
                        'tenant_id' => $tenant->id,
                        'order_id' => $order->id,

                        'duffel_passenger_id' =>
                        $passengerData['id']
                            ?? null,

                        'type' =>
                        $passengerData['type']
                            ?? null,

                        'title' =>
                        $passengerData['title']
                            ?? null,

                        'given_name' =>
                        $passengerData['given_name']
                            ?? null,

                        'family_name' =>
                        $passengerData['family_name']
                            ?? null,

                        'dob' =>
                        $passengerData['born_on']
                            ?? null,

                        'gender' =>
                        $passengerData['gender']
                            ?? null,

                        'email' =>
                        $passengerData['email']
                            ?? null,

                        'phone_number' =>
                        $passengerData['phone_number']
                            ?? null,

                        /*
                     * This keeps your application's original
                     * infant-to-adult relationship.
                     */
                        'infant_passenger_id' =>
                        $passengerData['infant_passenger_id']
                            ?? null,

                        'meta' => [
                            'loyalty_programme_accounts' =>
                            $passengerData['loyalty_programme_accounts'] ?? null,

                            'raw_passenger' => $passengerData,
                        ],
                    ]);
                }

                if (!empty($validated['booking_addons'])) {
                    foreach (
                        $validated['booking_addons']
                        as $bookingAddon
                    ) {
                        BookingAddon::create([
                            'tenant_id' => $order->tenant_id,
                            'order_id' => $order->id,

                            'addon_id' =>
                            $bookingAddon['addon_id'],

                            'addon_code' =>
                            $bookingAddon['addon_code'],

                            'addon_name' =>
                            $bookingAddon['addon_name'],

                            'price' => $this->normalizeDecimal(
                                $bookingAddon['price']
                                    ?? null
                            ),

                            'currency' => strtoupper(
                                $bookingAddon['currency']
                                    ?? $this->defaultCurrency()
                            ),

                            'meta' =>
                            $bookingAddon['meta']
                                ?? null,
                        ]);
                    }
                }

                if ($convertedOrderAddons !== null) {
                    OrderAddon::create(array_merge([
                        'tenant_id' => $order->tenant_id,
                        'order_id' => $order->id,
                        'currency' => $this->defaultCurrency(),
                    ], $convertedOrderAddons));
                }

                return $order->load('passengers');
            });

            /*
         * Activity logging should not cause a completed
         * flight booking to be reported as failed.
         */
            try {
                $this->activityLogger->log(
                    action: 'order.created',
                    request: $request,
                    tenant: $tenant,
                    actor: $authUser ?? $orderUser,
                    subject: $order,
                    title: 'Booking created',
                    description: "Booking {$order->booking_reference} was created.",
                    category: 'order',
                    properties: [
                        'order_id' => $order->id,
                        'duffel_order_id' =>
                        $order->duffel_order_id,
                        'booking_reference' =>
                        $order->booking_reference,
                        'status' => $order->status,
                        'total_amount' => $order->total_amount,
                        'currency' => $order->total_currency,
                        'seat_count' => count(
                            $seatData['selections']
                        ),
                    ],
                );
            } catch (\Throwable $loggingException) {
                Log::warning(
                    'Booking was created, but activity logging failed.',
                    [
                        'order_id' => $order->id,
                        'message' =>
                        $loggingException->getMessage(),
                    ]
                );
            }

            /*
         * Email/PDF errors should not make the frontend think
         * the Duffel order was not created.
         */
            $notificationSent = false;

            try {
                $pdf = Pdf::loadView(
                    'pdf.order-reference',
                    [
                        'tenant' => $tenant->name,
                        'order' => $order,
                    ]
                );

                $htmlBody = view(
                    'emails.order-issued',
                    [
                        'tenant' => $tenant->name,
                        'name' => $orderUser->name
                            ?? 'Customer',
                        'order' => $order,
                    ]
                )->render();

                MailService::sendMail(
                    $recipientEmail,
                    $tenant->name . ' Booking Confirmation',
                    $htmlBody,
                    $pdf->output(),
                    'booking-reference-' .
                        $order->booking_reference .
                        '.pdf'
                );

                $notificationSent = true;
            } catch (\Throwable $mailException) {
                Log::warning(
                    'Booking was created, but confirmation email failed.',
                    [
                        'order_id' => $order->id,
                        'recipient' => $recipientEmail,
                        'message' =>
                        $mailException->getMessage(),
                    ]
                );
            }

            return response()->json([
                'message' => 'Order created successfully',

                'order' =>
                \App\Http\Resources\OrderResource::make(
                    $order
                )->resolve(),

                'duffel_order' =>
                $this->imposeDefaultCurrency(
                    $duffelOrder
                ),

                'seat_selections' =>
                $this->imposeDefaultCurrency(
                    $seatData['selections']
                ),

                'seat_service_total' => [
                    'amount' =>
                    $this->convertMoneyAmount(
                        $seatData['service_total_amount'],
                        $seatData['payment_currency']
                    ),

                    'currency' =>
                    $this->defaultCurrency(),
                ],

                'notification_sent' => $notificationSent,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            $message = $e->getMessage();

            $status = match (true) {
                str_contains($message, '404') => 404,
                str_contains($message, '409') => 409,
                str_contains($message, '422') => 422,
                default => 500,
            };

            Log::error('Order creation failed.', [
                'offer_id' => $request->input('offer_id'),
                'tenant_key' => $request->input('tenantKey'),
                'message' => $message,
                'exception' => get_class($e),
            ]);

            return response()->json([
                'error' => 'Order creation failed',
                'message' => $message,
            ], $status);
        }
    }

    public function getOrder(string $orderId)
    {
        try {
            $order = $this->duffel->getOrder($orderId);

            return response()->json($this->imposeDefaultCurrency($order));
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to fetch order',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateOrder(Request $request, string $orderId)
    {
        try {
            $validated = $request->validate([
                'metadata' => 'nullable|array',
                'passengers' => 'nullable|array',
            ]);

            return response()->json($this->imposeDefaultCurrency($this->duffel->updateOrder($orderId, $validated)));
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Order update failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getAvailableServices(string $orderId)
    {
        try {
            return response()->json($this->imposeDefaultCurrency($this->duffel->getAvailableServices($orderId)));
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to fetch available services',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getSeatMaps(Request $request)
    {
        try {
            $validated = $request->validate([
                'offer_id' => 'required|string',
            ]);

            return response()->json(
                $this->imposeDefaultCurrency($this->duffel->getSeatMaps($validated['offer_id']))
            );
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to fetch seat maps',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function checkOrderRefundable(Request $request, int $orderId)
    {
        $order = Order::findOrFail($orderId);

        return data_get($order->meta, 'duffel_order.conditions.refund_before_departure');
    }

    public function checkOrderChangeable(Request $request, int $orderId)
    {
        $order = Order::findOrFail($orderId);

        return data_get($order->meta, 'duffel_order.conditions.change_before_departure');
    }

    public function createOrderCancellation(Request $request)
    {
        try {
            $validated = $request->validate([
                'order_id' => 'required',
                'tenantKey' => 'nullable|string|exists:tenants,key',
            ]);

            $order = $this->resolveOrderForCancellation($validated['order_id']);
            $tenant = $this->resolveTenantFromKey($validated['tenantKey'] ?? null);
            $this->assertOrderBelongsToTenant($order, $tenant);
            $validationError = $this->validateOrderCanBeCancelled($order);

            if ($validationError) {
                return $validationError;
            }

            $refundBeforeDeparture = data_get($order->meta, 'duffel_order.conditions.refund_before_departure');
            $isRefundable = null;

            if (is_bool($refundBeforeDeparture)) {
                $isRefundable = $refundBeforeDeparture;
            } elseif (is_array($refundBeforeDeparture) && array_key_exists('allowed', $refundBeforeDeparture)) {
                $isRefundable = (bool) data_get($refundBeforeDeparture, 'allowed');
            }

            $quoteResponse = $this->duffel->createOrderCancellation($order->duffel_order_id);
            $quote = $quoteResponse['data'] ?? [];
            $quoteSummary = $this->extractCancellationQuoteSummary($quoteResponse);
            $warnings = $quoteSummary['warnings'] ?? [];

            if (! is_array($warnings)) {
                $warnings = [$warnings];
            }

            if ($isRefundable === false) {
                $warnings[] = 'This booking is marked as non-refundable, but cancellation can still be requested.';
            }

            $warnings = array_values(array_filter($warnings, fn($warning) => $warning !== null && $warning !== ''));
            $quoteSummary['warnings'] = $warnings;
            $quoteSummary['refundability'] = [
                'is_refundable' => $isRefundable,
                'refund_before_departure' => $refundBeforeDeparture,
            ];

            $order->update([
                'status' => Order::STATUS_CANCELLATION_REQUESTED,
                'cancellation_status' => Order::CANCELLATION_STATUS_REQUESTED,
                'refund_status' => null,
                'meta' => $this->mergeOrderMeta($order, [
                    'cancellation' => array_filter([
                        'cancellation_id' => $quote['id'] ?? null,
                        'refund_amount' => $quoteSummary['refund_amount'],
                        'refund_currency' => $quoteSummary['refund_currency'],
                        'cancellation_fee' => $quoteSummary['cancellation_fee'],
                        'cancellation_fee_currency' => $quoteSummary['cancellation_fee_currency'],
                        'expires_at' => $quoteSummary['expires_at'],
                        'warnings' => $warnings,
                        'refundability' => $quoteSummary['refundability'],
                        'confirmed_at' => null,
                        'quote_created_at' => now()->toISOString(),
                        'cancellation_response' => $quoteResponse,
                    ], fn($value) => $value !== null),
                ]),
            ]);

            $this->activityLogger->log(
                action: 'order.cancellation_requested',
                request: $request,
                tenant: $order->tenant,
                actor: $request->user(),
                subject: $order,
                title: 'Cancellation requested',
                description: "Cancellation was requested for booking {$order->booking_reference}.",
                category: 'order',
                properties: [
                    'order_id' => $order->id,
                    'booking_reference' => $order->booking_reference,
                    'refund_amount' => $quoteSummary['refund_amount'],
                    'refund_currency' => $quoteSummary['refund_currency'],
                ],
            );

            return response()->json([
                'message' => 'Cancellation quote created successfully',
                'order_id' => $order->id,
                'duffel_order_id' => $order->duffel_order_id,
                'data' => $this->imposeDefaultCurrency($quoteResponse['data'] ?? $quoteResponse),
                'quote' => $this->imposeDefaultCurrency($quoteSummary),
            ]);
        } catch (\Throwable $e) {
            $status = str_contains($e->getMessage(), '422') ? 422 : 500;

            return response()->json([
                'error' => 'Order cancellation quote failed',
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function getOrderCancellation(string $cancellationId)
    {
        try {
            $response = $this->duffel->getOrderCancellation($cancellationId);

            return response()->json([
                'data' => $this->imposeDefaultCurrency($response['data'] ?? $response),
                'quote' => $this->imposeDefaultCurrency($this->extractCancellationQuoteSummary($response)),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to fetch order cancellation',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function confirmOrderCancellation(Request $request, string $cancellationId, int $orderId)
    {
        try {
            $validated = request()->validate([
                'tenantKey' => 'nullable|string|exists:tenants,key',
            ]);

            $order = Order::findOrFail($orderId);
            $tenant = $this->resolveTenantFromKey($validated['tenantKey'] ?? null);
            $this->assertOrderBelongsToTenant($order, $tenant);
            $validationError = $this->validateOrderCanBeCancelled($order);

            if ($validationError) {
                return $validationError;
            }

            $storedCancellation = data_get($order->meta, 'cancellation', []);

            if (($storedCancellation['cancellation_id'] ?? null) !== $cancellationId) {
                return response()->json([
                    'error' => 'Cancellation quote mismatch',
                    'message' => 'The provided cancellation quote does not belong to this order.',
                ], 422);
            }

            if ($this->isCancellationQuoteExpired($storedCancellation['expires_at'] ?? null)) {
                return response()->json([
                    'error' => 'Cancellation quote expired',
                    'message' => 'The cancellation quote has expired. Create a new quote before confirming.',
                ], 422);
            }

            $latestCancellation = $this->duffel->getOrderCancellation($cancellationId);
            $latestSummary = $this->extractCancellationQuoteSummary($latestCancellation);

            if ($this->isCancellationQuoteExpired($latestSummary['expires_at'])) {
                return response()->json([
                    'error' => 'Cancellation quote expired',
                    'message' => 'The cancellation quote has expired. Create a new quote before confirming.',
                ], 422);
            }

            $result = $this->duffel->confirmOrderCancellation($cancellationId);
            $confirmedSummary = $this->extractCancellationQuoteSummary($result);
            [$status, $refundStatus] = $this->determineCancellationState($confirmedSummary['refund_amount']);

            $order->update([
                'status' => $status,
                'cancellation_status' => Order::CANCELLATION_STATUS_CANCELLED,
                'refund_status' => $refundStatus,
                'meta' => $this->mergeOrderMeta($order, [
                    'cancellation' => array_filter([
                        'cancellation_id' => $cancellationId,
                        'refund_amount' => $confirmedSummary['refund_amount'],
                        'refund_currency' => $confirmedSummary['refund_currency'],
                        'cancellation_fee' => $confirmedSummary['cancellation_fee'],
                        'cancellation_fee_currency' => $confirmedSummary['cancellation_fee_currency'],
                        'expires_at' => $confirmedSummary['expires_at'],
                        'warnings' => $confirmedSummary['warnings'],
                        'confirmed_at' => $confirmedSummary['confirmed_at'] ?? now()->toISOString(),
                        'cancellation_response' => $result,
                    ], fn($value) => $value !== null),
                ]),
            ]);

            $this->activityLogger->log(
                action: 'order.cancellation_confirmed',
                request: $request,
                tenant: $order->tenant,
                actor: $request->user(),
                subject: $order,
                title: 'Cancellation confirmed',
                description: "Cancellation was confirmed for booking {$order->booking_reference}.",
                category: 'order',
                properties: [
                    'order_id' => $order->id,
                    'booking_reference' => $order->booking_reference,
                    'refund_amount' => $confirmedSummary['refund_amount'],
                    'refund_currency' => $confirmedSummary['refund_currency'],
                    'refund_status' => $refundStatus,
                ],
            );

            $orderRecipient = User::find($order->user_id);
            if ($orderRecipient?->email) {
                SendBladeMail::dispatch(
                    recipientEmail: $orderRecipient->email,
                    subject: 'Your booking has been cancelled',
                    view: 'emails.cancellation-confirmed',
                    data: [
                        'name' => $orderRecipient->name ?? 'Customer',
                        'tenantName' => $order->tenant?->name ?? config('app.name'),
                        'bookingReference' => $order->booking_reference,
                        'cancellationStatus' => Order::CANCELLATION_STATUS_CANCELLED,
                        'refundStatus' => $refundStatus,
                        'refundAmount' => $confirmedSummary['refund_amount'],
                        'refundCurrency' => $confirmedSummary['refund_currency'],
                    ],
                    logLabel: 'order cancellation',
                    context: [
                        'order_id' => $order->id,
                        'booking_reference' => $order->booking_reference,
                    ]
                );
            }

            return response()->json([
                'message' => 'Order cancellation confirmed successfully',
                'order_id' => $order->id,
                'status' => $status,
                'cancellation_status' => Order::CANCELLATION_STATUS_CANCELLED,
                'refund_status' => $refundStatus,
                'data' => $this->imposeDefaultCurrency($result['data'] ?? $result),
                'quote' => $this->imposeDefaultCurrency($confirmedSummary),
            ]);
        } catch (\Throwable $e) {
            $status = str_contains($e->getMessage(), '404') ? 404 : (str_contains($e->getMessage(), '422') ? 422 : 500);

            return response()->json([
                'error' => 'Order cancellation confirmation failed',
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function confirmOrderRefund(Request $request, int $orderId)
    {
        try {
            $validated = $request->validate([
                'tenantKey' => 'nullable|string|exists:tenants,key',
                'reference' => 'nullable|string|max:255',
                'notes' => 'nullable|string',
            ]);

            $order = Order::findOrFail($orderId);
            $tenant = $this->resolveTenantFromKey($validated['tenantKey'] ?? null);
            $this->assertOrderBelongsToTenant($order, $tenant);
            $cancellation = data_get($order->meta, 'cancellation', []);
            $refundAmount = $this->normalizeDecimal($cancellation['refund_amount'] ?? null);

            if ($refundAmount === null || (float) $refundAmount <= 0) {
                return response()->json([
                    'error' => 'Refund confirmation is not applicable',
                    'message' => 'This cancelled booking does not have a refundable amount to confirm.',
                ], 422);
            }

            if (
                $order->cancellation_status !== Order::CANCELLATION_STATUS_CANCELLED
                || $order->refund_status !== Order::REFUND_STATUS_PENDING
            ) {
                return response()->json([
                    'error' => 'Invalid refund state',
                    'message' => 'Only cancelled bookings with pending refunds can be marked as refunded.',
                ], 422);
            }

            $order->update([
                'status' => Order::STATUS_REFUNDED,
                'cancellation_status' => Order::CANCELLATION_STATUS_CANCELLED,
                'refund_status' => Order::REFUND_STATUS_REFUNDED,
                'meta' => $this->mergeOrderMeta($order, [
                    'cancellation' => array_filter([
                        'refund_confirmed_at' => now()->toISOString(),
                        'refund_confirmation' => array_filter([
                            'reference' => $validated['reference'] ?? null,
                            'notes' => $validated['notes'] ?? null,
                        ], fn($value) => $value !== null && $value !== ''),
                    ], fn($value) => $value !== null && $value !== []),
                ]),
            ]);

            $this->activityLogger->log(
                action: 'order.refund_confirmed',
                request: $request,
                tenant: $order->tenant,
                actor: $request->user(),
                subject: $order,
                title: 'Refund confirmed',
                description: "Refund was confirmed for booking {$order->booking_reference}.",
                category: 'order',
                properties: [
                    'order_id' => $order->id,
                    'booking_reference' => $order->booking_reference,
                    'reference' => $validated['reference'] ?? null,
                    'refund_status' => Order::REFUND_STATUS_REFUNDED,
                ],
            );

            $orderRecipient = User::find($order->user_id);
            if ($orderRecipient?->email) {
                SendBladeMail::dispatch(
                    recipientEmail: $orderRecipient->email,
                    subject: 'Your refund has been confirmed',
                    view: 'emails.refund-confirmed',
                    data: [
                        'name' => $orderRecipient->name ?? 'Customer',
                        'tenantName' => $order->tenant?->name ?? config('app.name'),
                        'bookingReference' => $order->booking_reference,
                        'refundStatus' => Order::REFUND_STATUS_REFUNDED,
                        'reference' => $validated['reference'] ?? null,
                        'notes' => $validated['notes'] ?? null,
                    ],
                    logLabel: 'order refund',
                    context: [
                        'order_id' => $order->id,
                        'booking_reference' => $order->booking_reference,
                    ]
                );
            }

            return response()->json([
                'message' => 'Refund confirmed successfully',
                'order_id' => $order->id,
                'status' => $order->fresh()->status,
                'cancellation_status' => $order->fresh()->cancellation_status,
                'refund_status' => $order->fresh()->refund_status,
            ]);
        } catch (\Throwable $e) {
            $status = str_contains($e->getMessage(), '404') ? 404 : 500;

            return response()->json([
                'error' => 'Refund confirmation failed',
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    private function resolveOrderForCancellation(string $orderReference): Order
    {
        $query = Order::query();

        if (ctype_digit($orderReference)) {
            $query->where('id', (int) $orderReference);
        }

        return $query
            ->orWhere('duffel_order_id', $orderReference)
            ->firstOrFail();
    }

    private function resolveTenantFromKey(?string $tenantKey): ?Tenant
    {
        if (! is_string($tenantKey) || trim($tenantKey) === '') {
            return null;
        }

        return Tenant::where('key', $tenantKey)->first();
    }

    private function assertOrderBelongsToTenant(Order $order, ?Tenant $tenant): void
    {
        if ($tenant && $order->tenant_id !== $tenant->id) {
            abort(404, 'Booking does not belong to the given tenant.');
        }
    }

    private function validateOrderCanBeCancelled(Order $order): ?\Illuminate\Http\JsonResponse
    {
        if ($order->cancellation_status === Order::CANCELLATION_STATUS_CANCELLED) {
            return response()->json([
                'error' => 'Order already cancelled',
                'message' => 'This booking is already in a final cancellation state.',
            ], 422);
        }

        return null;
    }

    private function extractCancellationQuoteSummary(array $response): array
    {
        $data = $response['data'] ?? $response;
        $warnings = data_get($data, 'warnings', $response['warnings'] ?? []);
        return $this->currencyConverter->convertPayload([
            'cancellation_id' => data_get($data, 'id'),
            'refund_amount' => $this->normalizeDecimal(
                data_get($data, 'refund_amount', data_get($data, 'refund.amount'))
            ),
            'refund_currency' => data_get($data, 'refund_currency')
                ?? data_get($data, 'refund.currency')
                ?? data_get($data, 'currency'),
            'cancellation_fee' => $this->normalizeDecimal(
                data_get($data, 'cancellation_fee')
                    ?? data_get($data, 'fee_amount')
                    ?? data_get($data, 'fee.amount')
            ),
            'cancellation_fee_currency' => data_get($data, 'cancellation_fee_currency')
                ?? data_get($data, 'fee_currency')
                ?? data_get($data, 'fee.currency')
                ?? data_get($data, 'refund_currency')
                ?? data_get($data, 'currency'),
            'expires_at' => data_get($data, 'expires_at'),
            'confirmed_at' => data_get($data, 'confirmed_at'),
            'warnings' => is_array($warnings) ? $warnings : [$warnings],
        ]);
    }

    private function determineCancellationState(?string $refundAmount): array
    {
        if ($refundAmount === null) {
            return [
                Order::STATUS_CANCELLED,
                Order::REFUND_STATUS_UNKNOWN,
            ];
        }

        if ((float) $refundAmount > 0) {
            return [
                Order::STATUS_CANCELLED,
                Order::REFUND_STATUS_PENDING,
            ];
        }

        return [
            Order::STATUS_CANCELLED,
            Order::REFUND_STATUS_NONE,
        ];
    }

    private function isCancellationQuoteExpired(?string $expiresAt): bool
    {
        if (!$expiresAt) {
            return false;
        }

        return Carbon::parse($expiresAt)->isPast();
    }

    private function mergeOrderMeta(Order $order, array $attributes): array
    {
        return array_replace_recursive($order->meta ?? [], $attributes);
    }

    private function convertMoneyAmount($amount, ?string $currency): ?string
    {
        return $this->currencyConverter->convertAmount($amount, $currency);
    }

    private function defaultCurrency(): string
    {
        return $this->currencyConverter->defaultCurrency();
    }

    private function imposeDefaultCurrency(mixed $value): mixed
    {
        return $this->currencyConverter->convertPayload($value);
    }

    private function normalizeDecimal($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function resolveLocalOrder(?string $orderReference): ?Order
    {
        if (! is_string($orderReference) || trim($orderReference) === '') {
            return null;
        }

        $query = Order::query();

        if (ctype_digit($orderReference)) {
            $query->where('id', (int) $orderReference);
        }

        return $query
            ->orWhere('duffel_order_id', $orderReference)
            ->first();
    }

    private function updateOrderChangeMeta(Order $order, array $attributes): void
    {
        $order->forceFill([
            'meta' => $this->mergeOrderMeta($order, [
                'change' => array_filter(
                    $attributes,
                    fn($value) => $value !== null && $value !== []
                ),
            ]),
        ])->save();
    }

    private function updateOrderCancellationMeta(Order $order, array $attributes): void
    {
        $order->forceFill([
            'meta' => $this->mergeOrderMeta($order, [
                'cancellation' => array_filter(
                    $attributes,
                    fn($value) => $value !== null && $value !== []
                ),
            ]),
        ])->save();
    }

    private function normalizeWorkflowStatus(?string $status): string
    {
        $normalized = strtolower(trim($status ?? ''));

        return $normalized === 'pending_confirmation' ? 'approved' : $normalized;
    }

    public function createOrderChangeRequest(Request $request)
    {
        try {
            $validated = $request->validate([
                'order_id' => 'required|string',
                'slices' => 'required|array',
                'slices.remove' => 'nullable|array',
                'slices.remove.*.slice_id' => 'required_with:slices.remove|string',
                'slices.add' => 'nullable|array',
                'slices.add.*.origin' => 'required_with:slices.add|string|size:3',
                'slices.add.*.destination' => 'required_with:slices.add|string|size:3',
                'slices.add.*.departure_date' => 'required_with:slices.add|date_format:Y-m-d',
                'slices.add.*.cabin_class' => 'nullable|string',
            ]);
            $order = $this->resolveLocalOrder($validated['order_id']);
            $payload = $validated;

            if ($order) {
                $payload['order_id'] = $order->duffel_order_id;
            }

            $response = $this->duffel->createOrderChangeRequest($payload);
            $data = $response['data'] ?? $response;

            if ($order) {
                $this->updateOrderChangeMeta($order, [
                    'request_id' => data_get($data, 'id'),
                    'status' => 'requested',
                    'requested_at' => now()->toISOString(),
                    'request_payload' => $payload,
                    'request_response' => $response,
                ]);

                $this->activityLogger->log(
                    action: 'order.change_requested',
                    request: $request,
                    tenant: $order->tenant,
                    actor: $request->user(),
                    subject: $order,
                    title: 'Reschedule requested',
                    description: "A reschedule request was created for booking {$order->booking_reference}.",
                    category: 'order',
                    properties: [
                        'order_id' => $order->id,
                        'booking_reference' => $order->booking_reference,
                        'request_id' => data_get($data, 'id'),
                    ],
                );

                $orderRecipient = User::find($order->user_id);
                if ($orderRecipient?->email) {
                    SendBladeMail::dispatch(
                        recipientEmail: $orderRecipient->email,
                        subject: 'Your reschedule request was received',
                        view: 'emails.reschedule-requested',
                        data: [
                            'name' => $orderRecipient->name ?? 'Customer',
                            'tenantName' => $order->tenant?->name ?? config('app.name'),
                            'bookingReference' => $order->booking_reference,
                            'requestId' => data_get($data, 'id'),
                        ],
                        logLabel: 'order reschedule request',
                        context: [
                            'order_id' => $order->id,
                            'booking_reference' => $order->booking_reference,
                        ]
                    );
                }
            }

            return response()->json($response);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Order change request failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getOrderChangeRequest(string $orderChangeRequestId)
    {
        try {
            return response()->json(
                $this->imposeDefaultCurrency($this->duffel->getOrderChangeRequest($orderChangeRequestId))
            );
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to fetch order change request',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getOrderChangeOffer(string $orderChangeOfferId)
    {
        try {
            return response()->json(
                $this->imposeDefaultCurrency($this->duffel->getOrderChangeOffer($orderChangeOfferId))
            );
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to fetch order change offer',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function createOrderChange(Request $request)
    {
        try {
            $validated = $request->validate([
                'selected_order_change_offer' => 'required|string',
            ]);
            $response = $this->duffel->createOrderChange($validated);
            $data = $response['data'] ?? $response;
            $order = $this->resolveLocalOrder((string) (
                data_get($data, 'order_id')
                ?? data_get($data, 'order.id')
                ?? data_get($data, 'order.data.id')
            ));

            if ($order) {
                $this->updateOrderChangeMeta($order, [
                    'order_change_id' => data_get($data, 'id'),
                    'selected_order_change_offer' => $validated['selected_order_change_offer'],
                    'status' => 'pending_confirmation',
                    'change_created_at' => now()->toISOString(),
                    'change_response' => $response,
                ]);

                $this->activityLogger->log(
                    action: 'order.change_created',
                    request: $request,
                    tenant: $order->tenant,
                    actor: $request->user(),
                    subject: $order,
                    title: 'Reschedule prepared',
                    description: "A reschedule change was prepared for booking {$order->booking_reference}.",
                    category: 'order',
                    properties: [
                        'order_id' => $order->id,
                        'booking_reference' => $order->booking_reference,
                        'order_change_id' => data_get($data, 'id'),
                    ],
                );

                $orderRecipient = User::find($order->user_id);
                if ($orderRecipient?->email) {
                    SendBladeMail::dispatch(
                        recipientEmail: $orderRecipient->email,
                        subject: 'Your updated itinerary is ready',
                        view: 'emails.reschedule-confirmed',
                        data: [
                            'name' => $orderRecipient->name ?? 'Customer',
                            'tenantName' => $order->tenant?->name ?? config('app.name'),
                            'bookingReference' => $order->booking_reference,
                            'orderChangeId' => data_get($data, 'id'),
                        ],
                        logLabel: 'order reschedule confirmed',
                        context: [
                            'order_id' => $order->id,
                            'booking_reference' => $order->booking_reference,
                        ]
                    );
                }
            }

            return response()->json($this->imposeDefaultCurrency($response));
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Order change creation failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function approveOrderChange(Request $request, string $orderId)
    {
        try {
            $validated = $request->validate([
                'tenantKey' => 'nullable|string|exists:tenants,key',
                'note' => 'nullable|string|max:1000',
            ]);

            $order = Order::findOrFail($orderId);
            $tenant = $this->resolveTenantFromKey($validated['tenantKey'] ?? null);
            $this->assertOrderBelongsToTenant($order, $tenant);

            $change = data_get($order->meta, 'change', []);
            $currentStatus = $this->normalizeWorkflowStatus((string) data_get($change, 'status'));

            if (in_array($currentStatus, ['rejected', 'confirmed'], true)) {
                return response()->json([
                    'error' => 'Change workflow is already finalized',
                    'message' => 'This reschedule has already been rejected or confirmed.',
                ], 422);
            }

            $this->updateOrderChangeMeta($order, [
                ...$change,
                'status' => 'approved',
                'approved_at' => now()->toISOString(),
                'approved_by' => $request->user()?->id,
                'approval_note' => $validated['note'] ?? null,
            ]);

            $this->activityLogger->log(
                action: 'order.change_approved',
                request: $request,
                tenant: $order->tenant,
                actor: $request->user(),
                subject: $order,
                title: 'Reschedule approved',
                description: "A reschedule was approved for booking {$order->booking_reference}.",
                category: 'order',
                properties: [
                    'order_id' => $order->id,
                    'booking_reference' => $order->booking_reference,
                    'status' => 'approved',
                ],
            );

            return response()->json([
                'message' => 'Reschedule approved successfully',
                'order_id' => $order->id,
                'status' => $order->status,
                'change_status' => 'approved',
                'order' => \App\Http\Resources\OrderResource::make($order->fresh()->load(['tenant', 'user', 'passengers']))->resolve(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to approve reschedule',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function rejectOrderChange(Request $request, string $orderId)
    {
        try {
            $validated = $request->validate([
                'tenantKey' => 'nullable|string|exists:tenants,key',
                'reason' => 'nullable|string|max:1000',
            ]);

            $order = Order::findOrFail($orderId);
            $tenant = $this->resolveTenantFromKey($validated['tenantKey'] ?? null);
            $this->assertOrderBelongsToTenant($order, $tenant);

            $change = data_get($order->meta, 'change', []);
            $currentStatus = $this->normalizeWorkflowStatus((string) data_get($change, 'status'));

            if ($currentStatus === 'confirmed') {
                return response()->json([
                    'error' => 'Change workflow is already finalized',
                    'message' => 'This reschedule has already been confirmed.',
                ], 422);
            }

            $this->updateOrderChangeMeta($order, [
                ...$change,
                'status' => 'rejected',
                'rejected_at' => now()->toISOString(),
                'rejected_by' => $request->user()?->id,
                'rejection_reason' => $validated['reason'] ?? null,
            ]);

            $this->activityLogger->log(
                action: 'order.change_rejected',
                request: $request,
                tenant: $order->tenant,
                actor: $request->user(),
                subject: $order,
                title: 'Reschedule rejected',
                description: "A reschedule was rejected for booking {$order->booking_reference}.",
                category: 'order',
                properties: [
                    'order_id' => $order->id,
                    'booking_reference' => $order->booking_reference,
                    'status' => 'rejected',
                ],
            );

            return response()->json([
                'message' => 'Reschedule rejected successfully',
                'order_id' => $order->id,
                'status' => $order->status,
                'change_status' => 'rejected',
                'order' => \App\Http\Resources\OrderResource::make($order->fresh()->load(['tenant', 'user', 'passengers']))->resolve(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to reject reschedule',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function approveOrderCancellation(Request $request, string $orderId)
    {
        try {
            $validated = $request->validate([
                'tenantKey' => 'nullable|string|exists:tenants,key',
                'note' => 'nullable|string|max:1000',
            ]);

            $order = Order::findOrFail($orderId);
            $tenant = $this->resolveTenantFromKey($validated['tenantKey'] ?? null);
            $this->assertOrderBelongsToTenant($order, $tenant);

            $cancellation = data_get($order->meta, 'cancellation', []);
            $currentStatus = $this->normalizeWorkflowStatus((string) data_get($cancellation, 'status'));

            if (in_array($currentStatus, ['rejected', 'confirmed'], true)) {
                return response()->json([
                    'error' => 'Cancellation workflow is already finalized',
                    'message' => 'This cancellation has already been rejected or confirmed.',
                ], 422);
            }

            $this->updateOrderCancellationMeta($order, [
                ...$cancellation,
                'status' => 'approved',
                'approved_at' => now()->toISOString(),
                'approved_by' => $request->user()?->id,
                'approval_note' => $validated['note'] ?? null,
            ]);

            $this->activityLogger->log(
                action: 'order.cancellation_approved',
                request: $request,
                tenant: $order->tenant,
                actor: $request->user(),
                subject: $order,
                title: 'Cancellation approved',
                description: "A cancellation was approved for booking {$order->booking_reference}.",
                category: 'order',
                properties: [
                    'order_id' => $order->id,
                    'booking_reference' => $order->booking_reference,
                    'status' => 'approved',
                ],
            );

            return response()->json([
                'message' => 'Cancellation approved successfully',
                'order_id' => $order->id,
                'status' => $order->status,
                'cancellation_status' => 'approved',
                'order' => \App\Http\Resources\OrderResource::make($order->fresh()->load(['tenant', 'user', 'passengers']))->resolve(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to approve cancellation',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function rejectOrderCancellation(Request $request, string $orderId)
    {
        try {
            $validated = $request->validate([
                'tenantKey' => 'nullable|string|exists:tenants,key',
                'reason' => 'nullable|string|max:1000',
            ]);

            $order = Order::findOrFail($orderId);
            $tenant = $this->resolveTenantFromKey($validated['tenantKey'] ?? null);
            $this->assertOrderBelongsToTenant($order, $tenant);

            $cancellation = data_get($order->meta, 'cancellation', []);
            $currentStatus = $this->normalizeWorkflowStatus((string) data_get($cancellation, 'status'));

            if ($currentStatus === 'confirmed') {
                return response()->json([
                    'error' => 'Cancellation workflow is already finalized',
                    'message' => 'This cancellation has already been confirmed.',
                ], 422);
            }

            $this->updateOrderCancellationMeta($order, [
                ...$cancellation,
                'status' => 'rejected',
                'rejected_at' => now()->toISOString(),
                'rejected_by' => $request->user()?->id,
                'rejection_reason' => $validated['reason'] ?? null,
            ]);

            $this->activityLogger->log(
                action: 'order.cancellation_rejected',
                request: $request,
                tenant: $order->tenant,
                actor: $request->user(),
                subject: $order,
                title: 'Cancellation rejected',
                description: "A cancellation was rejected for booking {$order->booking_reference}.",
                category: 'order',
                properties: [
                    'order_id' => $order->id,
                    'booking_reference' => $order->booking_reference,
                    'status' => 'rejected',
                ],
            );

            return response()->json([
                'message' => 'Cancellation rejected successfully',
                'order_id' => $order->id,
                'status' => $order->status,
                'cancellation_status' => 'rejected',
                'order' => \App\Http\Resources\OrderResource::make($order->fresh()->load(['tenant', 'user', 'passengers']))->resolve(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to reject cancellation',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getOrderChange(string $orderChangeId)
    {
        try {
            return response()->json($this->imposeDefaultCurrency($this->duffel->getOrderChange($orderChangeId)));
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to fetch order change',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function confirmOrderChange(Request $request, string $orderChangeId)
    {
        try {
            $validated = $request->validate([
                'payment' => 'nullable|array',
                'payment.type' => 'required_with:payment|string',
                'payment.amount' => 'required_with:payment|string',
                'payment.currency' => 'required_with:payment|string|size:3',
            ]);

            $payload = [];

            if (!empty($validated['payment'])) {
                $payload['payment'] = $validated['payment'];
            }

            $response = $this->duffel->confirmOrderChange($orderChangeId, $payload);
            $data = $response['data'] ?? $response;

            $order = $this->resolveLocalOrder((string) (
                data_get($data, 'order_id')
                ?? data_get($data, 'order.id')
                ?? data_get($data, 'order.data.id')
            ));

            if ($order) {
                $latestOrder = $this->imposeDefaultCurrency(
                    $this->duffel->getOrder($order->duffel_order_id)
                );
                $changeMeta = data_get($order->meta, 'change', []);
                $confirmedAt = data_get($data, 'confirmed_at')
                    ?? data_get($latestOrder, 'confirmed_at')
                    ?? now()->toISOString();
                $changeStatus = data_get($data, 'status')
                    ?? data_get($latestOrder, 'status')
                    ?? 'confirmed';

                $order->update([
                    'status' => Order::STATUS_RESCHEDULED,
                    'meta' => $this->mergeOrderMeta($order, [
                        'duffel_order' => $latestOrder,
                        'change' => array_filter([
                            'request_id' => data_get($changeMeta, 'request_id'),
                            'order_change_id' => data_get($changeMeta, 'order_change_id', $orderChangeId),
                            'selected_order_change_offer' => data_get($changeMeta, 'selected_order_change_offer'),
                            'status' => $changeStatus,
                            'requested_at' => data_get($changeMeta, 'requested_at'),
                            'change_created_at' => data_get($changeMeta, 'change_created_at'),
                            'confirmed_at' => $confirmedAt,
                            'request_payload' => data_get($changeMeta, 'request_payload'),
                            'request_response' => data_get($changeMeta, 'request_response'),
                            'change_response' => data_get($changeMeta, 'change_response'),
                            'confirm_response' => $response,
                            'previous_order_snapshot' => data_get($changeMeta, 'latest_order_snapshot')
                                ?? data_get($order->meta, 'duffel_order'),
                            'latest_order_snapshot' => $latestOrder,
                            'payment_difference' => data_get($data, 'payment_difference'),
                            'refund_amount' => data_get($data, 'refund_amount'),
                            'additional_payment_amount' => data_get($data, 'additional_payment_amount'),
                        ], fn($value) => $value !== null && $value !== []),
                    ]),
                ]);

                $order->loadMissing(['tenant', 'user']);

                $recipientEmail = $order->user?->email
                    ?? data_get($order->meta, 'contact_email');

                if ($recipientEmail) {
                    $htmlBody = view('emails.order-changed', [
                        'tenant' => $order->tenant?->name ?? config('app.name'),
                        'name' => $order->user?->name ?? 'Customer',
                        'order' => $order,
                        'change' => data_get($order->meta, 'change', []),
                    ])->render();

                    MailService::sendMail(
                        $recipientEmail,
                        ($order->tenant?->name ?? config('app.name')) . ' Booking Rescheduled',
                        $htmlBody
                    );
                }

                $this->activityLogger->log(
                    action: 'order.change_confirmed',
                    request: $request,
                    tenant: $order->tenant,
                    actor: $request->user(),
                    subject: $order,
                    title: 'Reschedule confirmed',
                    description: "A reschedule was confirmed for booking {$order->booking_reference}.",
                    category: 'order',
                    properties: [
                        'order_id' => $order->id,
                        'booking_reference' => $order->booking_reference,
                        'order_change_id' => $orderChangeId,
                    ],
                );

                return response()->json([
                    'message' => 'Order change confirmed successfully',
                    'order_id' => $order->id,
                    'status' => $order->status,
                    'change_status' => $changeStatus,
                    'data' => $latestOrder,
                    'summary' => data_get($data, 'summary'),
                ]);
            }

            return response()->json($this->imposeDefaultCurrency($response));
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Order change confirmation failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function prepareDuffelPassengers(
        array $passengers
    ): array {
        $adultIds = [];
        $infantIds = [];

        foreach ($passengers as $passenger) {
            $passengerId = $passenger['id'] ?? null;
            $type = $passenger['type'] ?? null;

            if (
                !is_string($passengerId) ||
                trim($passengerId) === ''
            ) {
                continue;
            }

            if ($type === 'adult') {
                $adultIds[] = $passengerId;
            }

            if ($type === 'infant_without_seat') {
                $infantIds[] = $passengerId;
            }
        }

        if (count($infantIds) > count($adultIds)) {
            throw ValidationException::withMessages([
                'passengers' => [
                    'Each infant without a seat must be linked to a different adult passenger.',
                ],
            ]);
        }

        /*
     * Key: adult passenger ID
     * Value: infant passenger ID
     */
        $infantLinks = [];
        $linkedInfantIds = [];
        $usedAdultIds = [];

        foreach ($passengers as $passenger) {
            if (
                ($passenger['type'] ?? null)
                !== 'infant_without_seat'
            ) {
                continue;
            }

            $infantId = $passenger['id'] ?? null;
            $adultId =
                $passenger['infant_passenger_id']
                ?? null;

            if (!$adultId) {
                continue;
            }

            if (!in_array($adultId, $adultIds, true)) {
                throw ValidationException::withMessages([
                    'passengers' => [
                        "The accompanying adult {$adultId} is not part of the selected offer.",
                    ],
                ]);
            }

            if (isset($usedAdultIds[$adultId])) {
                throw ValidationException::withMessages([
                    'passengers' => [
                        'An adult passenger cannot be assigned to more than one infant without a seat.',
                    ],
                ]);
            }

            $infantLinks[$adultId] = $infantId;
            $linkedInfantIds[$infantId] = true;
            $usedAdultIds[$adultId] = true;
        }

        /*
     * Automatically connect any unlinked infants to an
     * available adult.
     */
        foreach ($infantIds as $infantId) {
            if (isset($linkedInfantIds[$infantId])) {
                continue;
            }

            $availableAdultId = null;

            foreach ($adultIds as $adultId) {
                if (!isset($usedAdultIds[$adultId])) {
                    $availableAdultId = $adultId;
                    break;
                }
            }

            if (!$availableAdultId) {
                throw ValidationException::withMessages([
                    'passengers' => [
                        'No available adult passenger could be assigned to an infant.',
                    ],
                ]);
            }

            $infantLinks[$availableAdultId] = $infantId;
            $usedAdultIds[$availableAdultId] = true;
        }

        $duffelPassengers = [];

        foreach ($passengers as $passenger) {
            /*
         * The frontend relation is removed from the infant.
         */
            unset($passenger['infant_passenger_id']);

            $passengerId = $passenger['id'] ?? null;

            /*
         * Duffel expects infant_passenger_id on the
         * accompanying adult passenger.
         */
            if (
                ($passenger['type'] ?? null) === 'adult' &&
                is_string($passengerId) &&
                isset($infantLinks[$passengerId])
            ) {
                $passenger['infant_passenger_id'] =
                    $infantLinks[$passengerId];
            }

            $duffelPassengers[] = $passenger;
        }

        return $duffelPassengers;
    }

    private function prepareSelectedSeatServices(
        string $offerId,
        array $requestedServices,
        array $passengers,
        array $offer
    ): array {
        $offerAmount = $this->normalizeProviderAmount(
            $offer['total_amount'] ?? null
        );

        $paymentCurrency = strtoupper(
            trim((string) (
                $offer['total_currency']
                ?? ''
            ))
        );

        if (
            !preg_match(
                '/^[A-Z]{3}$/',
                $paymentCurrency
            )
        ) {
            throw ValidationException::withMessages([
                'offer_id' => [
                    'The selected offer does not contain a valid payment currency.',
                ],
            ]);
        }

        /*
     * No seats were selected.
     */
        if (empty($requestedServices)) {
            return [
                'duffel_services' => [],
                'selections' => [],
                'offer_amount' => $offerAmount,
                'service_total_amount' => '0.00',
                'payment_amount' => $offerAmount,
                'payment_currency' => $paymentCurrency,
            ];
        }

        /*
     * Always refetch the raw seat map. Do not rely on
     * frontend prices or seat availability.
     */
        $seatMapsResponse = $this->duffel->getSeatMaps(
            $offerId
        );

        $serviceIndex = $this->buildSeatServiceIndex(
            $seatMapsResponse
        );

        if (empty($serviceIndex)) {
            throw ValidationException::withMessages([
                'services' => [
                    'Seat selection is not available for this flight offer.',
                ],
            ]);
        }

        $passengerTypes = [];

        foreach ($passengers as $passenger) {
            $passengerId = $passenger['id'] ?? null;

            if (
                is_string($passengerId) &&
                trim($passengerId) !== ''
            ) {
                $passengerTypes[$passengerId] =
                    $passenger['type']
                    ?? null;
            }
        }

        $duffelServices = [];
        $selections = [];
        $serviceAmounts = [];

        /*
     * Prevent one passenger selecting multiple seats
     * for the same segment.
     */
        $usedPassengerSegments = [];

        /*
     * Prevent two passengers selecting the same physical
     * seat on the same segment.
     */
        $usedSegmentSeats = [];

        foreach ($requestedServices as $requestedService) {
            $serviceId = $requestedService['id'];

            $seatService = $serviceIndex[$serviceId]
                ?? null;

            if (!$seatService) {
                throw ValidationException::withMessages([
                    'services' => [
                        "Seat service {$serviceId} is unavailable or does not belong to this offer.",
                    ],
                ]);
            }

            $passengerId =
                $seatService['passenger_id']
                ?? null;

            if (
                !$passengerId ||
                !array_key_exists(
                    $passengerId,
                    $passengerTypes
                )
            ) {
                throw ValidationException::withMessages([
                    'services' => [
                        "Seat service {$serviceId} does not belong to a passenger in this booking.",
                    ],
                ]);
            }

            if (
                $passengerTypes[$passengerId]
                === 'infant_without_seat'
            ) {
                throw ValidationException::withMessages([
                    'services' => [
                        'An infant without a seat cannot have a seat service.',
                    ],
                ]);
            }

            $segmentId =
                $seatService['segment_id']
                ?? null;

            $seatDesignator =
                $seatService['seat_designator']
                ?? null;

            if (!$segmentId || !$seatDesignator) {
                throw ValidationException::withMessages([
                    'services' => [
                        "Seat service {$serviceId} does not contain valid segment and seat information.",
                    ],
                ]);
            }

            $serviceCurrency = strtoupper(
                trim((string) (
                    $seatService['currency']
                    ?? ''
                ))
            );

            /*
         * The provider payment cannot safely combine two
         * unrelated currencies.
         */
            if ($serviceCurrency !== $paymentCurrency) {
                throw ValidationException::withMessages([
                    'services' => [
                        "Seat {$seatDesignator} uses {$serviceCurrency}, but the selected offer uses {$paymentCurrency}.",
                    ],
                ]);
            }

            $serviceAmount =
                $this->normalizeProviderAmount(
                    $seatService['amount']
                        ?? null
                );

            $passengerSegmentKey =
                $segmentId . ':' . $passengerId;

            if (
                isset(
                    $usedPassengerSegments[$passengerSegmentKey]
                )
            ) {
                throw ValidationException::withMessages([
                    'services' => [
                        'A passenger can select only one seat for each flight segment.',
                    ],
                ]);
            }

            $segmentSeatKey =
                $segmentId . ':' . $seatDesignator;

            if (
                isset(
                    $usedSegmentSeats[$segmentSeatKey]
                )
            ) {
                throw ValidationException::withMessages([
                    'services' => [
                        "Seat {$seatDesignator} was selected by more than one passenger.",
                    ],
                ]);
            }

            $usedPassengerSegments[$passengerSegmentKey] = true;

            $usedSegmentSeats[$segmentSeatKey] = true;

            $duffelServices[] = [
                'id' => $serviceId,
                'quantity' => 1,
            ];

            $selections[] = [
                'service_id' => $serviceId,
                'passenger_id' => $passengerId,

                'slice_id' =>
                $seatService['slice_id']
                    ?? null,

                'segment_id' => $segmentId,

                'seat_designator' =>
                $seatDesignator,

                'cabin_class' =>
                $seatService['cabin_class']
                    ?? null,

                'amount' => $serviceAmount,
                'currency' => $serviceCurrency,

                'disclosures' =>
                $seatService['disclosures']
                    ?? [],
            ];

            $serviceAmounts[] = $serviceAmount;
        }

        $serviceTotalAmount =
            $this->sumDecimalAmounts(
                $serviceAmounts
            );

        $paymentAmount =
            $this->sumDecimalAmounts([
                $offerAmount,
                $serviceTotalAmount,
            ]);

        return [
            'duffel_services' => $duffelServices,
            'selections' => $selections,
            'offer_amount' => $offerAmount,
            'service_total_amount' =>
            $serviceTotalAmount,
            'payment_amount' => $paymentAmount,
            'payment_currency' => $paymentCurrency,
        ];
    }

    private function buildSeatServiceIndex(
        array $seatMapsResponse
    ): array {
        $serviceIndex = [];

        $seatMaps = data_get(
            $seatMapsResponse,
            'data',
            []
        );

        if (!is_array($seatMaps)) {
            return [];
        }

        foreach ($seatMaps as $seatMap) {
            if (!is_array($seatMap)) {
                continue;
            }

            $sliceId = data_get(
                $seatMap,
                'slice_id'
            );

            $segmentId = data_get(
                $seatMap,
                'segment_id'
            );

            $cabins = data_get(
                $seatMap,
                'cabins',
                []
            );

            if (!is_array($cabins)) {
                continue;
            }

            foreach ($cabins as $cabin) {
                if (!is_array($cabin)) {
                    continue;
                }

                $cabinClass = data_get(
                    $cabin,
                    'cabin_class'
                );

                $rows = data_get(
                    $cabin,
                    'rows',
                    []
                );

                if (!is_array($rows)) {
                    continue;
                }

                foreach ($rows as $row) {
                    $sections = data_get(
                        $row,
                        'sections',
                        []
                    );

                    if (!is_array($sections)) {
                        continue;
                    }

                    foreach ($sections as $section) {
                        $elements = data_get(
                            $section,
                            'elements',
                            []
                        );

                        if (!is_array($elements)) {
                            continue;
                        }

                        foreach ($elements as $element) {
                            if (
                                data_get(
                                    $element,
                                    'type'
                                ) !== 'seat'
                            ) {
                                continue;
                            }

                            $availableServices =
                                data_get(
                                    $element,
                                    'available_services',
                                    []
                                );

                            if (
                                !is_array(
                                    $availableServices
                                )
                            ) {
                                continue;
                            }

                            foreach (
                                $availableServices
                                as $service
                            ) {
                                if (!is_array($service)) {
                                    continue;
                                }

                                $serviceId = data_get(
                                    $service,
                                    'id'
                                );

                                if (
                                    !is_string($serviceId) ||
                                    $serviceId === ''
                                ) {
                                    continue;
                                }

                                $serviceIndex[$serviceId] = [
                                    'id' => $serviceId,

                                    'passenger_id' =>
                                    data_get(
                                        $service,
                                        'passenger_id'
                                    ),

                                    'slice_id' => $sliceId,
                                    'segment_id' => $segmentId,

                                    'element_id' =>
                                    data_get(
                                        $element,
                                        'id'
                                    ),

                                    'seat_designator' =>
                                    data_get(
                                        $element,
                                        'designator'
                                    ),

                                    'cabin_class' =>
                                    $cabinClass,

                                    'amount' =>
                                    data_get(
                                        $service,
                                        'total_amount'
                                    ),

                                    'currency' =>
                                    data_get(
                                        $service,
                                        'total_currency'
                                    ),

                                    'disclosures' =>
                                    data_get(
                                        $element,
                                        'disclosures',
                                        []
                                    ),
                                ];
                            }
                        }
                    }
                }
            }
        }

        return $serviceIndex;
    }

    private function extractConfirmedSeatAssignments(
        array $duffelOrder
    ): array {
        $assignments = [];

        $slices = data_get(
            $duffelOrder,
            'slices',
            []
        );

        if (!is_array($slices)) {
            return [];
        }

        foreach ($slices as $slice) {
            $sliceId = data_get(
                $slice,
                'id'
            );

            $segments = data_get(
                $slice,
                'segments',
                []
            );

            if (!is_array($segments)) {
                continue;
            }

            foreach ($segments as $segment) {
                $segmentId = data_get(
                    $segment,
                    'id'
                );

                $segmentPassengers = data_get(
                    $segment,
                    'passengers',
                    []
                );

                if (!is_array($segmentPassengers)) {
                    continue;
                }

                foreach (
                    $segmentPassengers
                    as $segmentPassenger
                ) {
                    $seat = data_get(
                        $segmentPassenger,
                        'seat'
                    );

                    if ($seat === null || $seat === '') {
                        continue;
                    }

                    $seatDesignator = null;

                    if (is_string($seat)) {
                        $seatDesignator = $seat;
                    }

                    if (is_array($seat)) {
                        $seatDesignator =
                            data_get(
                                $seat,
                                'designator'
                            )
                            ?? data_get(
                                $seat,
                                'name'
                            );
                    }

                    if (!$seatDesignator) {
                        continue;
                    }

                    $assignments[] = [
                        'slice_id' => $sliceId,
                        'segment_id' => $segmentId,

                        'passenger_id' =>
                        data_get(
                            $segmentPassenger,
                            'passenger_id'
                        )
                            ?? data_get(
                                $segmentPassenger,
                                'id'
                            ),

                        'seat_designator' =>
                        $seatDesignator,

                        'seat' => $seat,
                    ];
                }
            }
        }

        return $assignments;
    }

    private function normalizeProviderAmount(
        mixed $amount
    ): string {
        if (
            !is_string($amount) &&
            !is_int($amount) &&
            !is_float($amount)
        ) {
            throw new \InvalidArgumentException(
                'Invalid provider money amount.'
            );
        }

        $value = trim((string) $amount);

        if (
            !preg_match(
                '/^\d+(?:\.\d+)?$/',
                $value
            )
        ) {
            throw new \InvalidArgumentException(
                "Invalid provider money amount: {$value}"
            );
        }

        [$whole, $fraction] = array_pad(
            explode('.', $value, 2),
            2,
            ''
        );

        $whole = ltrim($whole, '0');

        if ($whole === '') {
            $whole = '0';
        }

        if ($fraction === '') {
            return $whole;
        }

        return $whole . '.' . $fraction;
    }

    private function sumDecimalAmounts(
        array $amounts
    ): string {
        if (empty($amounts)) {
            return '0.00';
        }

        $normalizedAmounts = [];
        $scale = 0;

        foreach ($amounts as $amount) {
            $normalized =
                $this->normalizeProviderAmount(
                    $amount
                );

            $normalizedAmounts[] = $normalized;

            $decimalPosition = strpos(
                $normalized,
                '.'
            );

            $currentScale = $decimalPosition === false
                ? 0
                : strlen($normalized)
                - $decimalPosition
                - 1;

            $scale = max($scale, $currentScale);
        }

        $totalUnits = '0';

        foreach (
            $normalizedAmounts
            as $normalized
        ) {
            [$whole, $fraction] = array_pad(
                explode('.', $normalized, 2),
                2,
                ''
            );

            $fraction = str_pad(
                $fraction,
                $scale,
                '0',
                STR_PAD_RIGHT
            );

            $units = ltrim(
                $whole . $fraction,
                '0'
            );

            if ($units === '') {
                $units = '0';
            }

            $totalUnits =
                $this->addUnsignedIntegerStrings(
                    $totalUnits,
                    $units
                );
        }

        if ($scale === 0) {
            return $totalUnits;
        }

        $totalUnits = str_pad(
            $totalUnits,
            $scale + 1,
            '0',
            STR_PAD_LEFT
        );

        return substr(
            $totalUnits,
            0,
            -$scale
        ) . '.' . substr(
            $totalUnits,
            -$scale
        );
    }

    private function addUnsignedIntegerStrings(
        string $left,
        string $right
    ): string {
        $leftIndex = strlen($left) - 1;
        $rightIndex = strlen($right) - 1;

        $carry = 0;
        $result = '';

        while (
            $leftIndex >= 0 ||
            $rightIndex >= 0 ||
            $carry > 0
        ) {
            $leftDigit = $leftIndex >= 0
                ? (int) $left[$leftIndex]
                : 0;

            $rightDigit = $rightIndex >= 0
                ? (int) $right[$rightIndex]
                : 0;

            $sum = $leftDigit
                + $rightDigit
                + $carry;

            $result =
                (string) ($sum % 10)
                . $result;

            $carry = intdiv($sum, 10);

            $leftIndex--;
            $rightIndex--;
        }

        $result = ltrim($result, '0');

        return $result === ''
            ? '0'
            : $result;
    }
}
