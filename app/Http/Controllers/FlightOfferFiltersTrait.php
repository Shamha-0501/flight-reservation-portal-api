<?php

namespace App\Http\Controllers;

trait FlightOfferFiltersTrait
{
    private function traitSafeArray($value): array
    {
        return is_array($value) ? $value : [];
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
            if (empty(array_intersect($inc, $this->traitSafeArray($c['airlines'] ?? null)))) return false;
        }

        if (!empty($f['excludeAirlines']) && is_array($f['excludeAirlines'])) {
            $exc = array_map('strtoupper', $f['excludeAirlines']);
            if (!empty(array_intersect($exc, $this->traitSafeArray($c['airlines'] ?? null)))) return false;
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
            if (!empty(array_intersect($avoid, $this->traitSafeArray($c['layoverAirports'] ?? null)))) return false;
        }

        if (!empty($f['onlyLayovers']) && is_array($f['onlyLayovers'])) {
            $only = array_map('strtoupper', $f['onlyLayovers']);
            if (empty(array_intersect($only, $this->traitSafeArray($c['layoverAirports'] ?? null)))) return false;
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

            foreach ($this->traitSafeArray($c['airlines'] ?? null) as $code) {
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

            foreach ($this->traitSafeArray($c['layoverAirports'] ?? null) as $ap) {
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
            fn ($a, $b) =>
                (($a['fromPrice'] ?? PHP_INT_MAX) <=> ($b['fromPrice'] ?? PHP_INT_MAX))
                ?: (($b['count'] ?? 0) <=> ($a['count'] ?? 0))
        );

        $layoverList = array_values($layovers);
        usort(
            $layoverList,
            fn ($a, $b) =>
                (($a['fromPrice'] ?? PHP_INT_MAX) <=> ($b['fromPrice'] ?? PHP_INT_MAX))
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
}