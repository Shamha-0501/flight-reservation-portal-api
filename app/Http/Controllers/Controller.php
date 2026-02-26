<?php

namespace App\Http\Controllers;

use Amadeus\Amadeus;
use Carbon\Carbon;

abstract class Controller
{
    protected $amadeus;
    private $client_id;
    private $client_secret;

    public function __construct()
    {
        $this->client_id = config('app.amadeus.client_id');
        $this->client_secret = config('app.amadeus.client_secret');

        try {
            $this->amadeus = Amadeus::builder($this->client_id, $this->client_secret)->build();
        } catch (\Exception $e) {
            throw new \Exception('Failed to initialize Amadeus client: ' . $e->getMessage());
        }
    }

    protected function getFlightOffers(
        $origin,
        $destination,
        $departureDate,
        $returnDate,
        $adults,
        $children = 0,
        $infants = 0,
        $travelClass = null,
        $maxPrice = null,
        $includedAirlineCodes = null
    ) {
        try {
            $departureDate = Carbon::parse($departureDate)->format('Y-m-d');
            $returnDate = $returnDate ? Carbon::parse($returnDate)->format('Y-m-d') : null;

            $params = [
                'originLocationCode' => strtoupper($origin),
                'destinationLocationCode' => strtoupper($destination),
                'departureDate' => $departureDate,
                'adults' => (int)$adults,
                'max' => 150,
            ];

            if ($returnDate) $params['returnDate'] = $returnDate;
            if ($children) $params['children'] = (int)$children;
            if ($infants) $params['infants'] = (int)$infants;
            if ($travelClass) $params['travelClass'] = $travelClass;

            // keep optional server-side filters (but don't over-restrict)
            if ($maxPrice !== null) $params['maxPrice'] = (float)$maxPrice;
            if (!empty($includedAirlineCodes)) {
                $params['includedAirlineCodes'] = implode(',', array_map('strtoupper', $includedAirlineCodes));
            }

            $response = $this->amadeus->getShopping()->getFlightOffers()->get($params);

            // SDK may return object OR array
            if (is_array($response)) {
                if (!isset($response[0])) return ['data' => [], 'dictionaries' => []];
                $body = $response[0]->getResponse()->getBody();
            } else {
                $body = $response->getResponse()->getBody();
            }

            $fullArray = json_decode($body, true);
            return $fullArray ?? ['data' => [], 'dictionaries' => []];
        } catch (\Exception $e) {
            throw new \Exception('Error fetching flight offers: ' . $e->getMessage());
        }
    }
}
