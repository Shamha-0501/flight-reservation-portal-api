<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\Duffel\DuffelService;
use Carbon\Carbon;

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

            $offers = $this->safeArray($offersResponse['data'] ?? []);
            $offers = $this->duffel->filterValidOffers($offers, 30);

            $normalizedAll = array_map(
                fn (array $offer) => $this->normalizeDuffelOffer($offer),
                $offers
            );

            $ranges = $this->buildRanges($normalizedAll);
            $facets = $this->buildFacets($normalizedAll);

            $filtered = array_values(array_filter(
                $normalizedAll,
                fn (array $offer) => $this->passesFilters($offer, $validated)
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

            return response()->json($offer);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to fetch offer',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function createOrder(Request $request)
    {
        try {
            $validated = $request->validate([
                'offer_id' => 'required|string',
                'passengers' => 'required|array|min:1',
                'passengers.*.id' => 'required|string',
                'passengers.*.type' => 'required|string|in:adult,child,infant_without_seat',
                'passengers.*.title' => 'required|string',
                'passengers.*.given_name' => 'required|string',
                'passengers.*.family_name' => 'required|string',
                'passengers.*.born_on' => 'required|date',
                'passengers.*.gender' => 'required|string',
                'passengers.*.email' => 'required|email',
                'passengers.*.phone_number' => 'required|string',
                'passengers.*.loyalty_programme_accounts' => 'nullable|array',
            ]);

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

            $order = $this->duffel->createOrder($payload);

            return response()->json($order);
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

    private function safeArray($value): array
    {
        return is_array($value) ? $value : [];
    }

    private function normalizeDuffelOffer(array $offer): array
    {
        $slices = $this->safeArray($offer['slices'] ?? null);

        $out = $slices[0] ?? null;
        $in  = $slices[1] ?? null;

        $outC = is_array($out) ? $this->computeDuffelSlice($out) : null;
        $inC  = is_array($in) ? $this->computeDuffelSlice($in) : null;

        $totalAmount = (float)($offer['total_amount'] ?? 0);
        $totalDuration = ($outC['durationMinutes'] ?? 0) + ($inC['durationMinutes'] ?? 0);
        $maxStops = max($outC['stops'] ?? 0, $inC['stops'] ?? 0);

        $airlines = array_values(array_unique(array_merge(
            $this->safeArray($outC['airlines'] ?? null),
            $this->safeArray($inC['airlines'] ?? null)
        )));

        $layovers = array_values(array_unique(array_merge(
            $this->safeArray($outC['layovers'] ?? null),
            $this->safeArray($inC['layovers'] ?? null)
        )));

        $baggage = $this->computeDuffelBaggage($offer);
        [$refundAvail, $changeAvail] = $this->computeDuffelAmenities($offer);

        $offer['computed'] = [
            'grandTotal' => $totalAmount,
            'airlines' => $airlines,
            'outbound' => $outC,
            'inbound' => $inC,
            'maxStops' => $maxStops,
            'layoverAirports' => $layovers,
            'totalDurationMinutes' => $totalDuration,
            'refundableAvailable' => $refundAvail,
            'changeableAvailable' => $changeAvail,
            'baggage' => $baggage,
        ];

        return $offer;
    }

    private function computeDuffelSlice(array $slice): array
    {
        $segments = $this->safeArray($slice['segments'] ?? null);
        $stops = max(count($segments) - 1, 0);

        $first = $segments[0] ?? null;
        $last = !empty($segments) ? $segments[count($segments) - 1] : null;

        $departAt = is_array($first) ? ($first['departing_at'] ?? null) : null;
        $arriveAt = is_array($last) ? ($last['arriving_at'] ?? null) : null;

        $durationMinutes = 0;
        if ($departAt && $arriveAt) {
            $durationMinutes = Carbon::parse($departAt)
                ->diffInMinutes(Carbon::parse($arriveAt));
        }

        $departMinuteOfDay = null;
        if ($departAt) {
            $dt = Carbon::parse($departAt);
            $departMinuteOfDay = ($dt->hour * 60) + $dt->minute;
        }

        $layovers = [];
        $layoverMinutes = 0;
        $airlines = [];

        foreach ($segments as $i => $seg) {
            if (!is_array($seg)) {
                continue;
            }

            $marketingCarrier = $seg['marketing_carrier'] ?? null;
            $operatingCarrier = $seg['operating_carrier'] ?? null;
            $destination = $seg['destination'] ?? null;

            if (is_array($marketingCarrier) && !empty($marketingCarrier['iata_code'])) {
                $airlines[] = strtoupper($marketingCarrier['iata_code']);
            } elseif (is_array($operatingCarrier) && !empty($operatingCarrier['iata_code'])) {
                $airlines[] = strtoupper($operatingCarrier['iata_code']);
            }

            if ($i < count($segments) - 1) {
                if (is_array($destination) && !empty($destination['iata_code'])) {
                    $layovers[] = strtoupper($destination['iata_code']);
                }

                $arr = $seg['arriving_at'] ?? null;
                $nextSeg = $segments[$i + 1] ?? null;
                $dep = is_array($nextSeg) ? ($nextSeg['departing_at'] ?? null) : null;

                if ($arr && $dep) {
                    $layoverMinutes += Carbon::parse($arr)
                        ->diffInMinutes(Carbon::parse($dep), false);
                }
            }
        }

        return [
            'stops' => $stops,
            'durationMinutes' => $durationMinutes,
            'departAt' => $departAt,
            'arriveAt' => $arriveAt,
            'departMinuteOfDay' => $departMinuteOfDay,
            'layovers' => array_values(array_unique($layovers)),
            'layoverMinutes' => $layoverMinutes,
            'airlines' => array_values(array_unique($airlines)),
        ];
    }

    private function computeDuffelBaggage(array $offer): array
    {
        $hasCarryOn = false;
        $hasChecked = false;
        $minCheckedBags = 0;
        $minCabinKg = null;

        $services = $this->safeArray($offer['available_services'] ?? null);
        foreach ($services as $service) {
            if (!is_array($service)) {
                continue;
            }

            $type = strtoupper((string)($service['type'] ?? ''));
            $text = strtoupper(json_encode($service) ?: '');

            if (str_contains($type, 'BAG') || str_contains($text, 'CHECKED')) {
                $hasChecked = true;
            }

            if (str_contains($text, 'CARRY') || str_contains($text, 'CABIN')) {
                $hasCarryOn = true;
            }
        }

        return [
            'hasCarryOn' => $hasCarryOn,
            'hasChecked' => $hasChecked,
            'minCheckedBags' => $minCheckedBags,
            'minCabinKg' => $minCabinKg,
        ];
    }

    private function computeDuffelAmenities(array $offer): array
    {
        $refund = false;
        $change = false;

        $conditions = $offer['conditions'] ?? null;
        $conditionsArray = is_array($conditions) ? $conditions : [];
        $text = strtoupper(json_encode($conditionsArray) ?: '');

        if (str_contains($text, 'REFUND')) {
            $refund = true;
        }

        if (str_contains($text, 'CHANGE')) {
            $change = true;
        }

        return [$refund, $change];
    }
}