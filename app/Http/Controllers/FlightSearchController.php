<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;

class FlightSearchController extends Controller
{
    public function searchFlightOffers(Request $request)
    {
        $validated = $request->validate([
            'originLocationCode' => 'required|string|max:10',
            'destinationLocationCode' => 'required|string|max:10',
            'departureDate' => 'required|date_format:Y-m-d|after_or_equal:today',
            'returnDate' => 'nullable|date_format:Y-m-d|after_or_equal:departureDate',
            'trip' => 'nullable|in:oneway,roundtrip',
            'adults' => 'required|integer|min:1',
            'children' => 'nullable|integer|min:0',
            'infants' => 'nullable|integer|min:0',
            'travelClass' => 'nullable|string',

            // Filters (for FE + backend filtering)
            'minPrice' => 'nullable|numeric|min:0',
            'maxPrice' => 'nullable|numeric|min:0',

            // Stops facet like Direct / 1 stop / 2+ stops
            'stops' => 'nullable|array',
            'stops.*' => 'in:0,1,2plus',

            // Baggage facet like Carry-on / Checked bag
            'baggage' => 'nullable|array',
            'baggage.*' => 'in:carryOn,checked',

            // Airlines (multi select)
            'includeAirlines' => 'nullable|array',
            'includeAirlines.*' => 'string|max:3',
            'excludeAirlines' => 'nullable|array',
            'excludeAirlines.*' => 'string|max:3',

            // Departure time sliders (minutes of day: 0..1439)
            'outDepartMin' => 'nullable|integer|min:0|max:1439',
            'outDepartMax' => 'nullable|integer|min:0|max:1439',
            'inDepartMin' => 'nullable|integer|min:0|max:1439',
            'inDepartMax' => 'nullable|integer|min:0|max:1439',

            // Duration slider (minutes)
            'minDurationMinutes' => 'nullable|integer|min:0',
            'maxDurationMinutes' => 'nullable|integer|min:1',

            // Layovers
            'avoidLayovers' => 'nullable|array',
            'avoidLayovers.*' => 'string|max:3',
            'onlyLayovers' => 'nullable|array',
            'onlyLayovers.*' => 'string|max:3',

            // Amenities
            'refundable' => 'nullable|boolean',
            'changeable' => 'nullable|boolean',

            // Checked bags count
            'minCheckedBags' => 'nullable|integer|min:0',

            // Sorting
            'sortBy' => 'nullable|in:best,price,duration',
            'sortDir' => 'nullable|in:asc,desc',
        ]);

        // Pull enough offers to power facets + sliders
        $isRoundTrip = ($validated['trip'] ?? 'roundtrip') === 'roundtrip';
        $returnDate = $isRoundTrip ? ($validated['returnDate'] ?? null) : null;

        $results = $this->getFlightOffers(
            $validated['originLocationCode'],
            $validated['destinationLocationCode'],
            $validated['departureDate'],
            $returnDate,
            $validated['adults'],
            $validated['children'] ?? 0,
            $validated['infants'] ?? 0,
            $validated['travelClass'] ?? null,
            // optional server-side filters (keep wide to not kill data)
            $validated['maxPrice'] ?? null,
            $validated['includeAirlines'] ?? null
        );

        $offers = $results['data'] ?? [];
        $dictionaries = $results['dictionaries'] ?? [];

        // Normalize all offers first (this powers facets/ranges)
        $normalizedAll = array_map(fn($o) => $this->normalizeOffer($o, $dictionaries), $offers);

        // Build ranges/facets from ALL offers (like Google Flights / Wego)
        $ranges = $this->buildRanges($normalizedAll);
        $facets = $this->buildFacets($normalizedAll, $dictionaries);

        // Apply filters to normalized offers
        $filtered = array_values(array_filter($normalizedAll, fn($o) => $this->passesFilters($o, $validated)));

        // Sorting
        $sortBy = $validated['sortBy'] ?? 'best';
        $sortDir = $validated['sortDir'] ?? 'asc';
        $filtered = $this->sortOffers($filtered, $sortBy, $sortDir);

        // Summary cards (best/cheapest/fastest) from FILTERED results
        $summary = $this->buildSummaryCards($filtered);

        // Applied filters (normalized / defaults)
        $appliedFilters = $this->buildAppliedFilters($validated, $ranges);

        return response()->json([
            'data' => $filtered,
            'meta' => [
                'count' => count($filtered),
                'currency' => $filtered[0]['price']['currency'] ?? ($normalizedAll[0]['price']['currency'] ?? null),

                // UI helpers (no FE calculations needed)
                'appliedFilters' => $appliedFilters,
                'ranges' => $ranges,
                'facets' => $facets,
                'summary' => $summary,
                'sort' => [
                    'current' => ['sortBy' => $sortBy, 'sortDir' => $sortDir],
                    'options' => [
                        ['sortBy' => 'best', 'label' => 'Best'],
                        ['sortBy' => 'price', 'label' => 'Cheapest'],
                        ['sortBy' => 'duration', 'label' => 'Fastest'],
                    ],
                ],
            ],
            'dictionaries' => $dictionaries,
        ], 200);
    }

    // -----------------------------
    // FILTERS
    // -----------------------------
    private function passesFilters(array $offer, array $f): bool
    {
        $c = $offer['computed'];

        // Price
        if (isset($f['minPrice']) && ($c['grandTotal'] ?? 0) < (float)$f['minPrice']) return false;
        if (isset($f['maxPrice']) && ($c['grandTotal'] ?? 0) > (float)$f['maxPrice']) return false;

        // Stops (checkbox set: [0,1,2plus])
        if (!empty($f['stops'])) {
            $stops = (int)($c['maxStops'] ?? 0);
            $ok = false;
            foreach ($f['stops'] as $s) {
                if ($s === '0' && $stops === 0) $ok = true;
                if ($s === '1' && $stops === 1) $ok = true;
                if ($s === '2plus' && $stops >= 2) $ok = true;
            }
            if (!$ok) return false;
        }

        // Baggage
        if (!empty($f['baggage'])) {
            $wantCarry = in_array('carryOn', $f['baggage'], true);
            $wantChecked = in_array('checked', $f['baggage'], true);

            if ($wantCarry && empty($c['baggage']['hasCarryOn'])) return false;
            if ($wantChecked && empty($c['baggage']['hasChecked'])) return false;
        }

        // Airlines include/exclude (use computed airlines)
        if (!empty($f['includeAirlines'])) {
            $inc = array_map('strtoupper', $f['includeAirlines']);
            if (empty(array_intersect($inc, $c['airlines'] ?? []))) return false;
        }
        if (!empty($f['excludeAirlines'])) {
            $exc = array_map('strtoupper', $f['excludeAirlines']);
            if (!empty(array_intersect($exc, $c['airlines'] ?? []))) return false;
        }

        // Departure time sliders
        if (isset($f['outDepartMin']) || isset($f['outDepartMax'])) {
            $m = $c['outbound']['departMinuteOfDay'] ?? null;
            if ($m === null) return false;
            if (isset($f['outDepartMin']) && $m < (int)$f['outDepartMin']) return false;
            if (isset($f['outDepartMax']) && $m > (int)$f['outDepartMax']) return false;
        }
        if (isset($f['inDepartMin']) || isset($f['inDepartMax'])) {
            // Only apply if return exists
            if (!empty($c['inbound'])) {
                $m = $c['inbound']['departMinuteOfDay'] ?? null;
                if ($m === null) return false;
                if (isset($f['inDepartMin']) && $m < (int)$f['inDepartMin']) return false;
                if (isset($f['inDepartMax']) && $m > (int)$f['inDepartMax']) return false;
            }
        }

        // Duration slider (total)
        if (isset($f['minDurationMinutes']) && ($c['totalDurationMinutes'] ?? 0) < (int)$f['minDurationMinutes']) return false;
        if (isset($f['maxDurationMinutes']) && ($c['totalDurationMinutes'] ?? 0) > (int)$f['maxDurationMinutes']) return false;

        // Layovers
        if (!empty($f['avoidLayovers'])) {
            $avoid = array_map('strtoupper', $f['avoidLayovers']);
            if (!empty(array_intersect($avoid, $c['layoverAirports'] ?? []))) return false;
        }
        if (!empty($f['onlyLayovers'])) {
            $only = array_map('strtoupper', $f['onlyLayovers']);
            if (empty(array_intersect($only, $c['layoverAirports'] ?? []))) return false;
        }

        // Refundable / changeable
        if (array_key_exists('refundable', $f) && $f['refundable'] !== null) {
            if ((bool)$f['refundable'] !== (bool)($c['refundableAvailable'] ?? false)) return false;
        }
        if (array_key_exists('changeable', $f) && $f['changeable'] !== null) {
            if ((bool)$f['changeable'] !== (bool)($c['changeableAvailable'] ?? false)) return false;
        }

        // Checked bags count
        if (isset($f['minCheckedBags'])) {
            if (($c['baggage']['minCheckedBags'] ?? 0) < (int)$f['minCheckedBags']) return false;
        }

        return true;
    }

    private function sortOffers(array $offers, string $sortBy, string $sortDir): array
    {
        usort($offers, function ($a, $b) use ($sortBy, $sortDir) {
            $ca = $a['computed'];
            $cb = $b['computed'];

            if ($sortBy === 'price') {
                $va = $ca['grandTotal'] ?? 0;
                $vb = $cb['grandTotal'] ?? 0;
            } elseif ($sortBy === 'duration') {
                $va = $ca['totalDurationMinutes'] ?? 0;
                $vb = $cb['totalDurationMinutes'] ?? 0;
            } else { // best
                // Simple “best” score: price + duration weight + stops penalty
                $va = ($ca['grandTotal'] ?? 0) + (($ca['totalDurationMinutes'] ?? 0) * 0.3) + (($ca['maxStops'] ?? 0) * 120);
                $vb = ($cb['grandTotal'] ?? 0) + (($cb['totalDurationMinutes'] ?? 0) * 0.3) + (($cb['maxStops'] ?? 0) * 120);
            }

            if ($va == $vb) return 0;
            $cmp = $va < $vb ? -1 : 1;
            return $sortDir === 'desc' ? -$cmp : $cmp;
        });

        return $offers;
    }

    // -----------------------------
    // NORMALIZATION (per offer)
    // -----------------------------
    private function normalizeOffer(array $offer, array $dictionaries): array
    {
        $grandTotal = (float)($offer['price']['grandTotal'] ?? 0);

        $itins = $offer['itineraries'] ?? [];
        $out = $itins[0] ?? null;
        $in  = $itins[1] ?? null;

        $outC = $out ? $this->computeItinerary($out) : null;
        $inC  = $in  ? $this->computeItinerary($in)  : null;

        $totalDuration = ($outC['durationMinutes'] ?? 0) + ($inC['durationMinutes'] ?? 0);
        $maxStops = max($outC['stops'] ?? 0, $inC['stops'] ?? 0);

        // Airlines involved: validating + all segment carriers
        $airlines = [];
        foreach (($offer['validatingAirlineCodes'] ?? []) as $code) $airlines[] = strtoupper($code);
        foreach ($itins as $itin) {
            foreach (($itin['segments'] ?? []) as $seg) {
                if (!empty($seg['carrierCode'])) $airlines[] = strtoupper($seg['carrierCode']);
            }
        }
        $airlines = array_values(array_unique($airlines));

        // Layover airports across both directions
        $layovers = array_values(array_unique(array_merge(
            $outC['layovers'] ?? [],
            $inC['layovers'] ?? []
        )));

        // Amenities (refund/change)
        [$refundAvail, $changeAvail] = $this->computeAmenities($offer);

        // Baggage (carry-on + checked)
        $baggage = $this->computeBaggage($offer);

        // Carrier names for UI
        $carrierNames = [];
        foreach ($airlines as $code) {
            $carrierNames[$code] = $dictionaries['carriers'][$code] ?? $code;
        }

        $offer['computed'] = [
            'grandTotal' => $grandTotal,
            'airlines' => $airlines,
            'airlineNames' => $carrierNames,

            'outbound' => $outC,
            'inbound' => $inC,

            'maxStops' => $maxStops,
            'layoverAirports' => $layovers,

            'totalDurationMinutes' => $totalDuration,

            'refundableAvailable' => $refundAvail,
            'changeableAvailable' => $changeAvail,

            'baggage' => $baggage,

            'bookableSeats' => (int)($offer['numberOfBookableSeats'] ?? 0),
        ];

        return $offer;
    }

    private function computeItinerary(array $itinerary): array
    {
        $segments = $itinerary['segments'] ?? [];
        $stops = max(count($segments) - 1, 0);

        $first = $segments[0] ?? null;
        $last = !empty($segments) ? $segments[count($segments) - 1] : null;

        $departAt = $first['departure']['at'] ?? null;
        $arriveAt = $last['arrival']['at'] ?? null;

        $durationMinutes = !empty($itinerary['duration']) ? $this->isoDurationToMinutes($itinerary['duration']) : 0;

        // minute-of-day for slider
        $departMinuteOfDay = null;
        if ($departAt) {
            $dt = Carbon::parse($departAt);
            $departMinuteOfDay = ($dt->hour * 60) + $dt->minute;
        }

        // Layovers (intermediate arrivals)
        $layovers = [];
        if (count($segments) >= 2) {
            for ($i = 0; $i < count($segments) - 1; $i++) {
                $iata = $segments[$i]['arrival']['iataCode'] ?? null;
                if ($iata) $layovers[] = strtoupper($iata);
            }
        }
        $layovers = array_values(array_unique($layovers));

        // Layover duration (sum)
        $layoverMinutes = 0;
        if (count($segments) >= 2) {
            for ($i = 0; $i < count($segments) - 1; $i++) {
                $arr = $segments[$i]['arrival']['at'] ?? null;
                $dep = $segments[$i + 1]['departure']['at'] ?? null;
                if ($arr && $dep) {
                    $layoverMinutes += Carbon::parse($arr)->diffInMinutes(Carbon::parse($dep), false);
                }
            }
        }

        return [
            'stops' => $stops,
            'durationMinutes' => $durationMinutes,
            'departAt' => $departAt,
            'arriveAt' => $arriveAt,
            'departMinuteOfDay' => $departMinuteOfDay,
            'layovers' => $layovers,
            'layoverMinutes' => $layoverMinutes,
        ];
    }

    private function isoDurationToMinutes(string $iso): int
    {
        try {
            $interval = new \DateInterval($iso);
            return ($interval->d * 24 * 60) + ($interval->h * 60) + $interval->i + (int)round($interval->s / 60);
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function computeAmenities(array $offer): array
    {
        $refund = false;
        $change = false;

        foreach (($offer['travelerPricings'] ?? []) as $tp) {
            foreach (($tp['fareDetailsBySegment'] ?? []) as $fds) {
                foreach (($fds['amenities'] ?? []) as $a) {
                    $desc = strtoupper($a['description'] ?? '');
                    if (str_contains($desc, 'REFUNDABLE')) $refund = true;
                    if (str_contains($desc, 'CHANGEABLE')) $change = true;
                }
            }
        }

        return [$refund, $change];
    }

    private function computeBaggage(array $offer): array
    {
        $minChecked = null;
        $hasCarryOn = false;
        $minCabinKg = null;

        foreach (($offer['travelerPricings'] ?? []) as $tp) {
            foreach (($tp['fareDetailsBySegment'] ?? []) as $fds) {
                // Checked
                $q = $fds['includedCheckedBags']['quantity'] ?? null;
                if ($q !== null) {
                    $q = (int)$q;
                    $minChecked = $minChecked === null ? $q : min($minChecked, $q);
                }

                // Cabin
                $cabinWeight = $fds['includedCabinBags']['weight'] ?? null;
                if ($cabinWeight !== null) {
                    $hasCarryOn = true;
                    $cabinWeight = (int)$cabinWeight;
                    $minCabinKg = $minCabinKg === null ? $cabinWeight : min($minCabinKg, $cabinWeight);
                }

                // Some responses use quantity instead of weight
                $cabinQty = $fds['includedCabinBags']['quantity'] ?? null;
                if ($cabinQty !== null && (int)$cabinQty > 0) {
                    $hasCarryOn = true;
                }
            }
        }

        $minChecked = $minChecked ?? 0;
        $hasChecked = $minChecked > 0;

        return [
            'hasCarryOn' => $hasCarryOn,
            'hasChecked' => $hasChecked,
            'minCheckedBags' => $minChecked,
            'minCabinKg' => $minCabinKg, // can be null
        ];
    }

    // -----------------------------
    // UI META (Ranges, Facets, Summary)
    // -----------------------------
    private function buildRanges(array $offers): array
    {
        $prices = [];
        $durations = [];
        $outMinutes = [];
        $inMinutes = [];
        $layoverMinutes = [];

        foreach ($offers as $o) {
            $c = $o['computed'];
            $prices[] = $c['grandTotal'] ?? 0;
            $durations[] = $c['totalDurationMinutes'] ?? 0;

            if (isset($c['outbound']['departMinuteOfDay'])) $outMinutes[] = $c['outbound']['departMinuteOfDay'];
            if (!empty($c['inbound']) && isset($c['inbound']['departMinuteOfDay'])) $inMinutes[] = $c['inbound']['departMinuteOfDay'];

            $layoverMinutes[] = ($c['outbound']['layoverMinutes'] ?? 0) + ($c['inbound']['layoverMinutes'] ?? 0);
        }

        $priceMin = !empty($prices) ? min($prices) : 0;
        $priceMax = !empty($prices) ? max($prices) : 0;

        $durMin = !empty($durations) ? min($durations) : 0;
        $durMax = !empty($durations) ? max($durations) : 0;

        $outMin = !empty($outMinutes) ? min($outMinutes) : 0;
        $outMax = !empty($outMinutes) ? max($outMinutes) : 1439;

        $inMin = !empty($inMinutes) ? min($inMinutes) : 0;
        $inMax = !empty($inMinutes) ? max($inMinutes) : 1439;

        $layMin = !empty($layoverMinutes) ? min($layoverMinutes) : 0;
        $layMax = !empty($layoverMinutes) ? max($layoverMinutes) : 0;

        return [
            'price' => [
                'min' => round($priceMin, 2),
                'max' => round($priceMax, 2),
            ],
            'totalDurationMinutes' => [
                'min' => (int)$durMin,
                'max' => (int)$durMax,
                // FE-friendly display too:
                'minLabel' => $this->minutesToLabel($durMin),
                'maxLabel' => $this->minutesToLabel($durMax),
            ],
            'departureTimeMinutesOfDay' => [
                'outbound' => ['min' => (int)$outMin, 'max' => (int)$outMax],
                'inbound' => ['min' => (int)$inMin, 'max' => (int)$inMax],
            ],
            'layoverMinutes' => [
                'min' => (int)$layMin,
                'max' => (int)$layMax,
                'minLabel' => $this->minutesToLabel($layMin),
                'maxLabel' => $this->minutesToLabel($layMax),
            ],
        ];
    }

    private function buildFacets(array $offers, array $dictionaries): array
    {
        // Each facet option: { key, label, count, fromPrice }
        $stops = [
            '0' => ['key' => '0', 'label' => 'Direct', 'count' => 0, 'fromPrice' => null],
            '1' => ['key' => '1', 'label' => '1 stop', 'count' => 0, 'fromPrice' => null],
            '2plus' => ['key' => '2plus', 'label' => '2+ stops', 'count' => 0, 'fromPrice' => null],
        ];

        $baggage = [
            'carryOn' => ['key' => 'carryOn', 'label' => 'Carry-on bag', 'count' => 0, 'fromPrice' => null],
            'checked' => ['key' => 'checked', 'label' => 'Checked bag', 'count' => 0, 'fromPrice' => null],
        ];

        $airlines = [];      // code => facet
        $layovers = [];      // airport => facet

        foreach ($offers as $o) {
            $c = $o['computed'];
            $price = $c['grandTotal'] ?? 0;

            // Stops facet uses maxStops (like your UI)
            $ms = (int)($c['maxStops'] ?? 0);
            if ($ms === 0) $this->facetHit($stops['0'], $price);
            elseif ($ms === 1) $this->facetHit($stops['1'], $price);
            else $this->facetHit($stops['2plus'], $price);

            // Baggage
            if (!empty($c['baggage']['hasCarryOn'])) $this->facetHit($baggage['carryOn'], $price);
            if (!empty($c['baggage']['hasChecked'])) $this->facetHit($baggage['checked'], $price);

            // Airlines facet: show per airline code (use validating + segment carriers already in computed)
            foreach (($c['airlines'] ?? []) as $code) {
                $code = strtoupper($code);
                if (!isset($airlines[$code])) {
                    $airlines[$code] = [
                        'key' => $code,
                        'label' => $dictionaries['carriers'][$code] ?? $code,
                        'count' => 0,
                        'fromPrice' => null,
                    ];
                }
                $this->facetHit($airlines[$code], $price);
            }

            // Layover airports
            foreach (($c['layoverAirports'] ?? []) as $ap) {
                $ap = strtoupper($ap);
                if (!isset($layovers[$ap])) {
                    $layovers[$ap] = [
                        'key' => $ap,
                        'label' => $ap,
                        'count' => 0,
                        'fromPrice' => null,
                    ];
                }
                $this->facetHit($layovers[$ap], $price);
            }
        }

        // Sort airlines/layovers like UI (by fromPrice asc, then count desc)
        $airlineList = array_values($airlines);
        usort($airlineList, fn($a,$b) => ($a['fromPrice'] ?? PHP_INT_MAX) <=> ($b['fromPrice'] ?? PHP_INT_MAX) ?: ($b['count'] <=> $a['count']));

        $layoverList = array_values($layovers);
        usort($layoverList, fn($a,$b) => ($a['fromPrice'] ?? PHP_INT_MAX) <=> ($b['fromPrice'] ?? PHP_INT_MAX) ?: ($b['count'] <=> $a['count']));

        return [
            'stops' => array_values($stops),
            'baggage' => array_values($baggage),
            'airlines' => $airlineList,
            'layoverAirports' => $layoverList,
        ];
    }

    private function facetHit(array &$facetOption, float $price): void
    {
        $facetOption['count']++;
        if ($facetOption['fromPrice'] === null || $price < $facetOption['fromPrice']) {
            $facetOption['fromPrice'] = round($price, 2);
        }
    }

    private function buildSummaryCards(array $filteredOffers): array
    {
        if (empty($filteredOffers)) {
            return [
                'best' => null,
                'cheapest' => null,
                'fastest' => null,
            ];
        }

        $best = $this->sortOffers($filteredOffers, 'best', 'asc')[0] ?? null;
        $cheapest = $this->sortOffers($filteredOffers, 'price', 'asc')[0] ?? null;
        $fastest = $this->sortOffers($filteredOffers, 'duration', 'asc')[0] ?? null;

        return [
            'best' => $best ? $this->summaryFromOffer($best, 'Best') : null,
            'cheapest' => $cheapest ? $this->summaryFromOffer($cheapest, 'Cheapest') : null,
            'fastest' => $fastest ? $this->summaryFromOffer($fastest, 'Fastest') : null,
        ];
    }

    private function summaryFromOffer(array $offer, string $label): array
    {
        $c = $offer['computed'];
        return [
            'label' => $label,
            'price' => [
                'amount' => $c['grandTotal'] ?? 0,
                'currency' => $offer['price']['currency'] ?? null,
            ],
            'duration' => [
                'minutes' => $c['totalDurationMinutes'] ?? 0,
                'label' => $this->minutesToLabel($c['totalDurationMinutes'] ?? 0),
            ],
            'stops' => $c['maxStops'] ?? 0,
            // optionally include an offer id reference for “select card”
            'offerId' => $offer['id'] ?? null,
        ];
    }

    private function buildAppliedFilters(array $validated, array $ranges): array
    {
        // Fill slider defaults if not present, so FE always has a stable state
        $outMin = $validated['outDepartMin'] ?? $ranges['departureTimeMinutesOfDay']['outbound']['min'];
        $outMax = $validated['outDepartMax'] ?? $ranges['departureTimeMinutesOfDay']['outbound']['max'];
        $inMin  = $validated['inDepartMin'] ?? $ranges['departureTimeMinutesOfDay']['inbound']['min'];
        $inMax  = $validated['inDepartMax'] ?? $ranges['departureTimeMinutesOfDay']['inbound']['max'];

        $minDur = $validated['minDurationMinutes'] ?? $ranges['totalDurationMinutes']['min'];
        $maxDur = $validated['maxDurationMinutes'] ?? $ranges['totalDurationMinutes']['max'];

        $minPrice = $validated['minPrice'] ?? $ranges['price']['min'];
        $maxPrice = $validated['maxPrice'] ?? $ranges['price']['max'];

        return [
            'price' => ['min' => $minPrice, 'max' => $maxPrice],
            'stops' => $validated['stops'] ?? [],              // ["0","1","2plus"]
            'baggage' => $validated['baggage'] ?? [],          // ["carryOn","checked"]
            'airlines' => [
                'include' => array_map('strtoupper', $validated['includeAirlines'] ?? []),
                'exclude' => array_map('strtoupper', $validated['excludeAirlines'] ?? []),
            ],
            'departureTimes' => [
                'outbound' => ['min' => (int)$outMin, 'max' => (int)$outMax],
                'inbound' => ['min' => (int)$inMin, 'max' => (int)$inMax],
            ],
            'durationMinutes' => [
                'min' => (int)$minDur, 'max' => (int)$maxDur,
            ],
            'layovers' => [
                'avoid' => array_map('strtoupper', $validated['avoidLayovers'] ?? []),
                'only' => array_map('strtoupper', $validated['onlyLayovers'] ?? []),
            ],
            'amenities' => [
                'refundable' => $validated['refundable'] ?? null,
                'changeable' => $validated['changeable'] ?? null,
            ],
            'minCheckedBags' => $validated['minCheckedBags'] ?? 0,
        ];
    }

    private function minutesToLabel(int $minutes): string
    {
        $minutes = max(0, $minutes);
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return "{$h} h {$m} m";
    }
}



