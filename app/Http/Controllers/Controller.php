<?php

namespace App\Http\Controllers;

use Amadeus\Amadeus;
use Amadeus\Exceptions\ResponseException;
use Carbon\Carbon;
use Exception;

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

    /**
     * POST flight-offers using the v2 Flight Offers Search API.
     *
     * @param  array  $payload  Validated request body (same structure as Amadeus v2 shopping/flight-offers)
     * @return array            ['data' => [...], 'dictionaries' => [...]] or empty arrays on error
     */
    protected function postFlightOffers(array $payload): array
    {
        try {
            // Ensure the SDK is available
            $amadeus = $this->amadeus;

            // Convert payload to JSON string (required by Amadeus SDK post())
            $body = json_encode($payload);

            // Call Amadeus v2 shopping/flight-offers POST
            $response = $amadeus
                ->getShopping()
                ->getFlightOffers()
                ->post($body);

            // Parse SDK response (array of response objects)
            if (is_array($response) && isset($response[0])) {
                $bodyStr = $response[0]->getResponse()->getBody();
            } else {
                $bodyStr = $response->getResponse()->getBody();
            }

            $fullArray = json_decode($bodyStr, true);

            return $fullArray ?? ['data' => [], 'dictionaries' => []];
        } catch (ResponseException $e) {
            throw new \Exception('Amadeus Flight Offers POST error: ' . $e->getMessage());
        } catch (\Exception $e) {
            throw new \Exception('Error posting flight offers: ' . $e->getMessage());
        }
    }

    protected function postFlightOffersPrice(array $flightOffer, bool $fareRules = false): array
    {
        try {
            $params = [];

            if ($fareRules) {
                $params['include'] = 'detailed-fare-rules';
            }

            $body = json_encode([
                'data' => [
                    'type' => 'flight-offers-pricing',
                    'flightOffers' => [$flightOffer],
                ],
            ], JSON_THROW_ON_ERROR);

            $response = $this->amadeus
                ->getShopping()
                ->getFlightOffers()
                ->getPricing()
                ->post($body, $params ?: null);

            $bodyStr = (string) $response->getResponse()->getBody();

            return json_decode($bodyStr, true, 512, JSON_THROW_ON_ERROR);
        } catch (ResponseException $e) {
            throw new \Exception(
                'Amadeus Flight Offers Price POST error: ' . $e->getMessage(),
                0,
                $e
            );
        } catch (\Throwable $e) {
            throw new \Exception(
                'Error posting flight offers price: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    protected function postFlightCreateOrder(array $data): array
    {
        try {
            $body = json_encode([
                'data' => $data,
            ], JSON_THROW_ON_ERROR);

            $response = $this->amadeus
                ->getBooking()
                ->getFlightOrders()
                ->post($body);

            $httpResponse = $response->getResponse();

            if (!$httpResponse) {
                throw new \RuntimeException('No HTTP response returned from Amadeus.');
            }

            $rawBody = $httpResponse->getBody();

            $bodyContents = is_string($rawBody)
                ? $rawBody
                : $rawBody->getContents();

            if ($bodyContents === '' || $bodyContents === null) {
                return [];
            }

            $decoded = json_decode($bodyContents, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Invalid JSON returned from Amadeus: ' . json_last_error_msg());
            }

            return $decoded ?? [];
        } catch (ResponseException $e) {
            $apiBody = null;

            try {
                if (method_exists($e, 'getResponse') && $e->getResponse()) {
                    $res = $e->getResponse();
                    $rawBody = $res->getBody();

                    $apiBody = is_string($rawBody)
                        ? $rawBody
                        : $rawBody->getContents();
                }
            } catch (\Throwable $ignored) {
            }
            throw new \Exception('Amadeus flight order request failed.', 0, $e);
        } catch (\Throwable $e) {
            throw new \Exception('Flight order request failed before a valid response was returned.', 0, $e);
        }
    }
}
