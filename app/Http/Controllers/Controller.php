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
        // $nonStop = false,
        // $currencyCode = 'USD',
        // $maxPrice = 500,
        // $includedAirlineCodes = null,
        // $excludedAirlineCodes = null,
        // $includedCheckedBagsOnly = true,
    ) {
        try {
            $departureDate = Carbon::parse($departureDate)->format('Y-m-d');
            $returnDate = $returnDate ? Carbon::parse($returnDate)->format('Y-m-d') : null;

            $params = [
                'originLocationCode' => strtoupper($origin),
                'destinationLocationCode' => strtoupper($destination),
                'departureDate' => $departureDate,
                'adults' => (int) $adults,
                'max' => 1,

                // 'nonStop' => $nonStop,
                // 'currencyCode' => $currencyCode,
                // 'maxPrice' => $maxPrice,
                // 'includedAirlineCodes' => $includedAirlineCodes,   // don't combine with excludedAirlineCodes
                // // 'excludedAirlineCodes' => $excludedAirlineCodes,
                // 'includedCheckedBagsOnly' => $includedCheckedBagsOnly,
            ];

            if ($returnDate) $params['returnDate'] = $returnDate;
            if ($children) $params['children'] = (int) $children;
            if ($infants) $params['infants'] = (int) $infants;
            if ($travelClass) $params['travelClass'] = $travelClass; // must be ECONOMY / BUSINESS etc.

            $response = $this->amadeus->getShopping()->getFlightOffers()->get($params);

            $fullArray = json_decode($response[0]->getResponse()->getBody(), true);

            return $fullArray;
        } catch (\Exception $e) {
            throw new \Exception('Error fetching flight offers: ' . $e->getMessage());
        }
    }
}
