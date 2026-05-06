<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderAddon;
use App\Models\Passenger;
use App\Models\Tenant;
use Illuminate\Http\Request;
use App\Services\Duffel\DuffelService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FlightController extends Controller
{
    use FlightOfferFiltersTrait;

    protected DuffelService $duffel;

    public function __construct(DuffelService $duffel)
    {
        $this->duffel = $duffel;
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
                    'currency' => $filtered[0]['total_currency']
                        ?? ($normalizedAll[0]['total_currency'] ?? null),
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
                'offer' => $offer,
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

            return response()->json($paymentIntent);
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

            return response()->json($paymentIntent);
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
            ]);

            return DB::transaction(function () use ($validated) {
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

                if (!is_array($duffelOrder) || empty($duffelOrder['id'])) {
                    throw new \Exception('Invalid Duffel order response');
                }

                $tenant = Tenant::where('key', $validated['tenantKey'])->first();

                if (!$tenant) {
                    throw new \Exception('Invalid tenant.');
                }

                $order = Order::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $validated['user_id'] ?? null,

                    'duffel_order_id' => $duffelOrder['id'],
                    'booking_reference' => $duffelOrder['booking_reference'] ?? null,
                    'type' => $duffelOrder['type'] ?? 'instant',
                    'status' => $duffelOrder['status'] ?? 'created',

                    'base_amount' => $duffelOrder['base_amount'] ?? $offer['base_amount'] ?? null,
                    'base_currency' => $duffelOrder['base_currency'] ?? $offer['base_currency'] ?? null,

                    'tax_amount' => $duffelOrder['tax_amount'] ?? $offer['tax_amount'] ?? null,
                    'tax_currency' => $duffelOrder['tax_currency'] ?? $offer['tax_currency'] ?? null,

                    'total_amount' => $duffelOrder['total_amount'] ?? $offer['total_amount'] ?? null,
                    'total_currency' => $duffelOrder['total_currency'] ?? $offer['total_currency'] ?? null,

                    'synced_at' => now(),
                    'void_window_ends_at' => $duffelOrder['void_window_ends_at'] ?? null,

                    'meta' => [
                        'offer' => $offer,
                        'duffel_order' => $duffelOrder,
                    ],
                ]);

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

                if (!empty($validated['addons'])) {
                    OrderAddon::create(array_merge([
                        'tenant_id' => $order->tenant_id,
                        'order_id' => $order->id,
                        'currency' => $order->total_currency,
                    ], $validated['addons']));
                }

                return response()->json([
                    'message' => 'Order created successfully',
                    'order' => $order->load('passengers'),
                    'duffel_order' => $duffelOrder,
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

            return response()->json($order);
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

            return response()->json($this->duffel->updateOrder($orderId, $validated));
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
            return response()->json($this->duffel->getAvailableServices($orderId));
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
                $this->duffel->getSeatMaps($validated['offer_id'])
            );
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to fetch seat maps',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function createOrderCancellation(Request $request)
    {
        try {
            $validated = $request->validate([
                'order_id' => 'required|string',
            ]);

            return response()->json(
                $this->duffel->createOrderCancellation($validated['order_id'])
            );
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Order cancellation quote failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getOrderCancellation(string $cancellationId)
    {
        try {
            return response()->json($this->duffel->getOrderCancellation($cancellationId));
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to fetch order cancellation',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function confirmOrderCancellation(string $cancellationId)
    {
        try {
            return response()->json($this->duffel->confirmOrderCancellation($cancellationId));
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Order cancellation confirmation failed',
                'message' => $e->getMessage(),
            ], 500);
        }
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

            return response()->json($this->duffel->createOrderChangeRequest($validated));
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
                $this->duffel->getOrderChangeRequest($orderChangeRequestId)
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
                $this->duffel->getOrderChangeOffer($orderChangeOfferId)
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

            return response()->json($this->duffel->createOrderChange($validated));
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Order change creation failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getOrderChange(string $orderChangeId)
    {
        try {
            return response()->json($this->duffel->getOrderChange($orderChangeId));
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
            ]);

            return response()->json(
                $this->duffel->confirmOrderChange($orderChangeId, $validated)
            );
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Order change confirmation failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
