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
use Illuminate\Http\Request;
use App\Services\Duffel\DuffelService;
use App\Services\MailService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
    )
    {
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
                'tenantKey' => 'required|string',
                'offer_id' => 'required|string',
                'passengers' => 'required|array|min:1',
                'passengers.*.id' => 'required|string',
                'passengers.*.type' => 'required|string|in:adult,child,infant_without_seat',
                'passengers.*.title' => 'required|string',
                'passengers.*.given_name' => 'required|string',
                'passengers.*.family_name' => 'required|string',
                'passengers.*.born_on' => 'required|date',
                'passengers.*.gender' => 'required|string',
                'passengers.*.email' => 'nullable|email',
                'passengers.*.phone_number' => 'nullable|string',
                'passengers.*.loyalty_programme_accounts' => 'nullable|array',
                'passengers.*.infant_passenger_id' => 'nullable|string',

                // Optional addons
                'addons' => 'nullable|array',
                'booking_addons' => 'nullable|array',
                'booking_addons.*.addon_id' => 'required_with:booking_addons|integer|exists:addons,id',
                'booking_addons.*.addon_code' => 'required_with:booking_addons|string|max:255',
                'booking_addons.*.addon_name' => 'required_with:booking_addons|string|max:255',
                'booking_addons.*.price' => 'required_with:booking_addons|numeric|min:0',
                'booking_addons.*.currency' => 'required_with:booking_addons|string|size:3',
                'booking_addons.*.meta' => 'nullable|array',
                'agency_markup' => 'nullable|array',
                'agency_markup.enabled' => 'required_with:agency_markup|boolean',
                'agency_markup.mode' => 'required_with:agency_markup|in:percentage,fixed',
                'agency_markup.value' => 'required_with:agency_markup|numeric|min:0',
                'agency_markup.amount' => 'nullable|numeric|min:0',
                'agency_markup.currency' => 'required_with:agency_markup|string|size:3',
                'agency_markup.label' => 'nullable|string|max:255',
                'contact_email' => 'nullable|email',
            ]);

            $authUser = $request->user();

            $orderEmail = $validated['contact_email']
                ?? collect($validated['passengers'])->pluck('email')->filter()->first();

            $orderUser = null;

            if ($authUser) {
                $orderUser = $authUser;
            } elseif ($orderEmail) {
                $orderUser = User::where('email', $orderEmail)->first();
            }

            return DB::transaction(function () use ($validated, $orderUser, $authUser, $request) {
                $offerResponse = $this->duffel->getOffer($validated['offer_id']);
                $offer = $offerResponse['data'] ?? null;

                if (!is_array($offer) || empty($offer)) {
                    return response()->json([
                        'error' => 'Offer not found',
                    ], 422);
                }

                $payload = [
                    'selected_offers' => [$validated['offer_id']],
                    'payments' => [
                        [
                            'type' => 'balance',
                            'amount' => $offer['total_amount'] ?? null,
                            'currency' => $offer['total_currency'] ?? null,
                        ],
                    ],
                    'passengers' => $validated['passengers'],
                ];

                $duffelOrderResponse = $this->duffel->createOrder($payload);
                $duffelOrder = $duffelOrderResponse['data'] ?? $duffelOrderResponse;
                logger()->info("stage-1");
                if (!is_array($duffelOrder) || empty($duffelOrder['id'])) {
                    throw new \Exception('Invalid Duffel order response');
                }

                $tenant = Tenant::where('key', $validated['tenantKey'])->first();

                if (!$tenant) {
                    throw new \Exception('Invalid tenant.');
                }

                $order = Order::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $orderUser->id,

                    'duffel_order_id' => $duffelOrder['id'],
                    'booking_reference' => $duffelOrder['booking_reference'] ?? null,
                    'type' => $duffelOrder['type'] ?? 'instant',
                    'status' => 'Booked',
                    'cancellation_status' => Order::CANCELLATION_STATUS_NONE,
                    'refund_status' => null,

                    'base_amount' => $this->convertMoneyAmount(
                        $duffelOrder['base_amount'] ?? $offer['base_amount'] ?? null,
                        $duffelOrder['base_currency'] ?? $offer['base_currency'] ?? null
                    ),
                    'base_currency' => $this->defaultCurrency(),

                    'tax_amount' => $this->convertMoneyAmount(
                        $duffelOrder['tax_amount'] ?? $offer['tax_amount'] ?? null,
                        $duffelOrder['tax_currency'] ?? $offer['tax_currency'] ?? null
                    ),
                    'tax_currency' => $this->defaultCurrency(),

                    'total_amount' => $this->convertMoneyAmount(
                        $duffelOrder['total_amount'] ?? $offer['total_amount'] ?? null,
                        $duffelOrder['total_currency'] ?? $offer['total_currency'] ?? null
                    ),
                    'total_currency' => $this->defaultCurrency(),

                    'synced_at' => now(),
                    'void_window_ends_at' => $duffelOrder['void_window_ends_at'] ?? null,

                    'meta' => [
                        'offer' => $offer,
                        'duffel_order' => $duffelOrder,
                        'agency_markup' => $validated['agency_markup'] ?? null,
                    ],
                ]);
                logger()->info("stage-2");

                foreach ($validated['passengers'] as $passengerData) {
                    Passenger::create([
                        'tenant_id' => $tenant->id,
                        'order_id' => $order->id,

                        'duffel_passenger_id' => $passengerData['id'] ?? null,
                        'type' => $passengerData['type'] ?? null,
                        'title' => $passengerData['title'] ?? null,
                        'given_name' => $passengerData['given_name'] ?? null,
                        'family_name' => $passengerData['family_name'] ?? null,
                        'dob' => $passengerData['born_on'] ?? null,
                        'gender' => $passengerData['gender'] ?? null,
                        'email' => $passengerData['email'] ?? null,
                        'phone_number' => $passengerData['phone_number'] ?? null,
                        'infant_passenger_id' => $passengerData['infant_passenger_id'] ?? null,

                        'meta' => [
                            'loyalty_programme_accounts' => $passengerData['loyalty_programme_accounts'] ?? null,
                            'raw_passenger' => $passengerData,
                        ],
                    ]);
                }
                logger()->info("stage-3");

                if (!empty($validated['booking_addons'])) {
                    foreach ($validated['booking_addons'] as $bookingAddon) {
                        BookingAddon::create([
                            'tenant_id' => $order->tenant_id,
                            'order_id' => $order->id,
                            'addon_id' => $bookingAddon['addon_id'],
                            'addon_code' => $bookingAddon['addon_code'],
                            'addon_name' => $bookingAddon['addon_name'],
                            'price' => $this->normalizeDecimal($bookingAddon['price'] ?? null),
                            'currency' => strtoupper($bookingAddon['currency'] ?? $this->defaultCurrency()),
                            'meta' => $bookingAddon['meta'] ?? null,
                        ]);
                    }
                }

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
                        'duffel_order_id' => $order->duffel_order_id,
                        'booking_reference' => $order->booking_reference,
                        'status' => $order->status,
                        'total_amount' => $order->total_amount,
                        'currency' => $order->total_currency,
                    ],
                );
                logger()->info("stage-4");

                if (!empty($validated['addons'])) {
                    $addons = $this->currencyConverter->convertPayload($validated['addons']);

                    OrderAddon::create(array_merge([
                        'tenant_id' => $order->tenant_id,
                        'order_id' => $order->id,
                        'currency' => $this->defaultCurrency(),
                    ], $addons));
                }

                $pdf = Pdf::loadView('pdf.order-reference', [
                    'tenant' => $tenant->name,
                    'order' => $order->load('passengers'),
                ]);

                $htmlBody = view('emails.order-issued', [
                    'tenant' => $tenant->name,
                    'name' => $orderUser?->name ?? 'Customer',
                    'order' => $order,
                ])->render();

                MailService::sendMail(
                    $orderUser->email,
                    $tenant->name . ' Booking Confirmation',
                    $htmlBody,
                    $pdf->output(),
                    'booking-reference-' . $order->booking_reference . '.pdf'
                );

                return response()->json([
                    'message' => 'Order created successfully',
                    'order' => \App\Http\Resources\OrderResource::make($order->load('passengers'))->resolve(),
                    'duffel_order' => $this->imposeDefaultCurrency($duffelOrder),
                ]);
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            $status = str_contains($e->getMessage(), '422') ? 422 : 500;

            return response()->json([
                'error' => 'Order creation failed',
                'message' => $e->getMessage(),
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

            $warnings = array_values(array_filter($warnings, fn ($warning) => $warning !== null && $warning !== ''));
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

    public function confirmOrderCancellation( Request $request,string $cancellationId, int $orderId)
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

            if ($order->cancellation_status !== Order::CANCELLATION_STATUS_CANCELLED
                || $order->refund_status !== Order::REFUND_STATUS_PENDING) {
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
                    fn ($value) => $value !== null && $value !== []
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
                    fn ($value) => $value !== null && $value !== []
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
            }

            return response()->json($this->imposeDefaultCurrency($response));
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
                        ], fn ($value) => $value !== null && $value !== []),
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
}
