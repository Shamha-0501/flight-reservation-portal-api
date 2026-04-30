<?php

namespace App\Http\Controllers;

use Illuminate\Support\Carbon;

trait FlightOfferFiltersTrait
{
    private function safeArray($value): array
    {
        return is_array($value) ? $value : [];
    }

    public function filterValidOffers(array $offers, int $minSecondsToExpiry = 300): array
    {
        return array_values(array_filter($offers, function (array $offer) use ($minSecondsToExpiry) {
            $expiresAt = $offer['expires_at'] ?? null;

            if (!$expiresAt) {
                return true;
            }

            $now = Carbon::now('UTC');
            $expires = Carbon::parse($expiresAt)->utc();

            return $expires->greaterThan($now->copy()->addSeconds($minSecondsToExpiry));
        }));
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

    private function passesFilters(array $offer, array $f): bool
    {
        $c = is_array($offer['computed'] ?? null) ? $offer['computed'] : [];

        if (isset($f['minPrice']) && ($c['grandTotal'] ?? 0) < (float)$f['minPrice']) return false;
        if (isset($f['maxPrice']) && ($c['grandTotal'] ?? 0) > (float)$f['maxPrice']) return false;

        if (!empty($f['stops']) && is_array($f['stops'])) {
            $stops = (int)($c['maxStops'] ?? 0);
            $ok = false;

            foreach ($f['stops'] as $s) {
                if ($s === '0' && $stops === 0) $ok = true;
                if ($s === '1' && $stops === 1) $ok = true;
                if ($s === '2plus' && $stops >= 2) $ok = true;
            }

            if (!$ok) return false;
        }

        if (!empty($f['baggage']) && is_array($f['baggage'])) {
            $wantCarry = in_array('carryOn', $f['baggage'], true);
            $wantChecked = in_array('checked', $f['baggage'], true);

            if ($wantCarry && empty($c['baggage']['hasCarryOn'])) return false;
            if ($wantChecked && empty($c['baggage']['hasChecked'])) return false;
        }

        if (!empty($f['includeAirlines']) && is_array($f['includeAirlines'])) {
            $inc = array_map('strtoupper', $f['includeAirlines']);
            if (empty(array_intersect($inc, $this->safeArray($c['airlines'] ?? null)))) return false;
        }

        if (!empty($f['excludeAirlines']) && is_array($f['excludeAirlines'])) {
            $exc = array_map('strtoupper', $f['excludeAirlines']);
            if (!empty(array_intersect($exc, $this->safeArray($c['airlines'] ?? null)))) return false;
        }

        if (isset($f['outDepartMin']) || isset($f['outDepartMax'])) {
            $m = $c['outbound']['departMinuteOfDay'] ?? null;
            if ($m === null) return false;
            if (isset($f['outDepartMin']) && $m < (int)$f['outDepartMin']) return false;
            if (isset($f['outDepartMax']) && $m > (int)$f['outDepartMax']) return false;
        }

        if (isset($f['inDepartMin']) || isset($f['inDepartMax'])) {
            if (!empty($c['inbound']) && is_array($c['inbound'])) {
                $m = $c['inbound']['departMinuteOfDay'] ?? null;
                if ($m === null) return false;
                if (isset($f['inDepartMin']) && $m < (int)$f['inDepartMin']) return false;
                if (isset($f['inDepartMax']) && $m > (int)$f['inDepartMax']) return false;
            }
        }

        if (isset($f['minDurationMinutes']) && ($c['totalDurationMinutes'] ?? 0) < (int)$f['minDurationMinutes']) return false;
        if (isset($f['maxDurationMinutes']) && ($c['totalDurationMinutes'] ?? 0) > (int)$f['maxDurationMinutes']) return false;

        if (!empty($f['avoidLayovers']) && is_array($f['avoidLayovers'])) {
            $avoid = array_map('strtoupper', $f['avoidLayovers']);
            if (!empty(array_intersect($avoid, $this->safeArray($c['layoverAirports'] ?? null)))) return false;
        }

        if (!empty($f['onlyLayovers']) && is_array($f['onlyLayovers'])) {
            $only = array_map('strtoupper', $f['onlyLayovers']);
            if (empty(array_intersect($only, $this->safeArray($c['layoverAirports'] ?? null)))) return false;
        }

        if (array_key_exists('refundable', $f) && $f['refundable'] !== null) {
            if ((bool)$f['refundable'] !== (bool)($c['refundableAvailable'] ?? false)) return false;
        }

        if (array_key_exists('changeable', $f) && $f['changeable'] !== null) {
            if ((bool)$f['changeable'] !== (bool)($c['changeableAvailable'] ?? false)) return false;
        }

        if (isset($f['minCheckedBags'])) {
            if (($c['baggage']['minCheckedBags'] ?? 0) < (int)$f['minCheckedBags']) return false;
        }

        return true;
    }

    private function sortOffers(array $offers, string $sortBy, string $sortDir): array
    {
        usort($offers, function ($a, $b) use ($sortBy, $sortDir) {
            $ca = is_array($a['computed'] ?? null) ? $a['computed'] : [];
            $cb = is_array($b['computed'] ?? null) ? $b['computed'] : [];

            if ($sortBy === 'price') {
                $va = $ca['grandTotal'] ?? 0;
                $vb = $cb['grandTotal'] ?? 0;
            } elseif ($sortBy === 'duration') {
                $va = $ca['totalDurationMinutes'] ?? 0;
                $vb = $cb['totalDurationMinutes'] ?? 0;
            } else {
                $va = ($ca['grandTotal'] ?? 0)
                    + (($ca['totalDurationMinutes'] ?? 0) * 0.3)
                    + (($ca['maxStops'] ?? 0) * 120);

                $vb = ($cb['grandTotal'] ?? 0)
                    + (($cb['totalDurationMinutes'] ?? 0) * 0.3)
                    + (($cb['maxStops'] ?? 0) * 120);
            }

            if ($va == $vb) return 0;

            $cmp = $va < $vb ? -1 : 1;
            return $sortDir === 'desc' ? -$cmp : $cmp;
        });

        return $offers;
    }

    private function buildRanges(array $offers): array
    {
        $prices = [];
        $durations = [];
        $outMinutes = [];
        $inMinutes = [];
        $layoverMinutes = [];

        foreach ($offers as $o) {
            $c = is_array($o['computed'] ?? null) ? $o['computed'] : [];

            $prices[] = $c['grandTotal'] ?? 0;
            $durations[] = $c['totalDurationMinutes'] ?? 0;

            if (isset($c['outbound']['departMinuteOfDay'])) {
                $outMinutes[] = $c['outbound']['departMinuteOfDay'];
            }

            if (!empty($c['inbound']) && is_array($c['inbound']) && isset($c['inbound']['departMinuteOfDay'])) {
                $inMinutes[] = $c['inbound']['departMinuteOfDay'];
            }

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
                'minLabel' => $this->minutesToLabel((int)$durMin),
                'maxLabel' => $this->minutesToLabel((int)$durMax),
            ],
            'departureTimeMinutesOfDay' => [
                'outbound' => ['min' => (int)$outMin, 'max' => (int)$outMax],
                'inbound' => ['min' => (int)$inMin, 'max' => (int)$inMax],
            ],
            'layoverMinutes' => [
                'min' => (int)$layMin,
                'max' => (int)$layMax,
                'minLabel' => $this->minutesToLabel((int)$layMin),
                'maxLabel' => $this->minutesToLabel((int)$layMax),
            ],
        ];
    }

    private function buildFacets(array $offers): array
    {
        $stops = [
            '0' => ['key' => '0', 'label' => 'Direct', 'count' => 0, 'fromPrice' => null],
            '1' => ['key' => '1', 'label' => '1 stop', 'count' => 0, 'fromPrice' => null],
            '2plus' => ['key' => '2plus', 'label' => '2+ stops', 'count' => 0, 'fromPrice' => null],
        ];

        $baggage = [
            'carryOn' => ['key' => 'carryOn', 'label' => 'Carry-on bag', 'count' => 0, 'fromPrice' => null],
            'checked' => ['key' => 'checked', 'label' => 'Checked bag', 'count' => 0, 'fromPrice' => null],
        ];

        $airlines = [];
        $layovers = [];

        foreach ($offers as $o) {
            $c = is_array($o['computed'] ?? null) ? $o['computed'] : [];
            $price = (float)($c['grandTotal'] ?? 0);

            $ms = (int)($c['maxStops'] ?? 0);
            if ($ms === 0) {
                $this->facetHit($stops['0'], $price);
            } elseif ($ms === 1) {
                $this->facetHit($stops['1'], $price);
            } else {
                $this->facetHit($stops['2plus'], $price);
            }

            if (!empty($c['baggage']['hasCarryOn'])) {
                $this->facetHit($baggage['carryOn'], $price);
            }

            if (!empty($c['baggage']['hasChecked'])) {
                $this->facetHit($baggage['checked'], $price);
            }

            foreach ($this->safeArray($c['airlines'] ?? null) as $code) {
                $code = strtoupper((string)$code);
                if ($code === '') {
                    continue;
                }

                if (!isset($airlines[$code])) {
                    $airlines[$code] = [
                        'key' => $code,
                        'label' => $code,
                        'count' => 0,
                        'fromPrice' => null,
                    ];
                }

                $this->facetHit($airlines[$code], $price);
            }

            foreach ($this->safeArray($c['layoverAirports'] ?? null) as $ap) {
                $ap = strtoupper((string)$ap);
                if ($ap === '') {
                    continue;
                }

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

        $airlineList = array_values($airlines);
        usort(
            $airlineList,
            fn($a, $b) => (($a['fromPrice'] ?? PHP_INT_MAX) <=> ($b['fromPrice'] ?? PHP_INT_MAX))
                ?: (($b['count'] ?? 0) <=> ($a['count'] ?? 0))
        );

        $layoverList = array_values($layovers);
        usort(
            $layoverList,
            fn($a, $b) => (($a['fromPrice'] ?? PHP_INT_MAX) <=> ($b['fromPrice'] ?? PHP_INT_MAX))
                ?: (($b['count'] ?? 0) <=> ($a['count'] ?? 0))
        );

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
        $c = is_array($offer['computed'] ?? null) ? $offer['computed'] : [];

        return [
            'label' => $label,
            'price' => [
                'amount' => $c['grandTotal'] ?? 0,
                'currency' => $offer['total_currency'] ?? null,
            ],
            'duration' => [
                'minutes' => $c['totalDurationMinutes'] ?? 0,
                'label' => $this->minutesToLabel((int)($c['totalDurationMinutes'] ?? 0)),
            ],
            'stops' => $c['maxStops'] ?? 0,
            'offerId' => $offer['id'] ?? null,
        ];
    }

    private function buildAppliedFilters(array $validated, array $ranges): array
    {
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
            'stops' => is_array($validated['stops'] ?? null) ? $validated['stops'] : [],
            'baggage' => is_array($validated['baggage'] ?? null) ? $validated['baggage'] : [],
            'airlines' => [
                'include' => array_map('strtoupper', is_array($validated['includeAirlines'] ?? null) ? $validated['includeAirlines'] : []),
                'exclude' => array_map('strtoupper', is_array($validated['excludeAirlines'] ?? null) ? $validated['excludeAirlines'] : []),
            ],
            'departureTimes' => [
                'outbound' => ['min' => (int)$outMin, 'max' => (int)$outMax],
                'inbound' => ['min' => (int)$inMin, 'max' => (int)$inMax],
            ],
            'durationMinutes' => [
                'min' => (int)$minDur,
                'max' => (int)$maxDur,
            ],
            'layovers' => [
                'avoid' => array_map('strtoupper', is_array($validated['avoidLayovers'] ?? null) ? $validated['avoidLayovers'] : []),
                'only' => array_map('strtoupper', is_array($validated['onlyLayovers'] ?? null) ? $validated['onlyLayovers'] : []),
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

    private function determineSeatMapStatus($seatMaps)
    {
        $data = $seatMaps['data'] ?? [];

        if (empty($data)) {
            return 'unavailable';
        }

        foreach ($data as $map) {
            if (!empty($map['cabins'])) {
                foreach ($map['cabins'] as $cabin) {
                    foreach ($cabin['rows'] ?? [] as $row) {
                        foreach ($row['sections'] ?? [] as $section) {
                            foreach ($section['elements'] ?? [] as $element) {

                                if (
                                    $element['type'] === 'seat' &&
                                    !empty($element['designator'])
                                ) {
                                    // Seat exists

                                    if (!empty($element['available_services'])) {
                                        return 'available'; // selectable seats exist
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return 'view_only'; // seat map exists but no selectable seats
    }
}
