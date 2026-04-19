<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\Duffel\DuffelService;

class FlightController extends Controller
{
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
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Place search failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function searchFlights(Request $request)
    {
        try {
            $validated = $request->validate([
                'originLocationCode' => 'required|string|size:3',
                'destinationLocationCode' => 'required|string|size:3',
                'departureDate' => 'required|date|after_or_equal:today',
                'returnDate' => 'nullable|date|after_or_equal:departureDate',
                'adults' => 'required|integer|min:1',
                'children' => 'nullable|integer|min:0',
                'infants' => 'nullable|integer|min:0',
                'travelClass' => 'nullable|string|in:ECONOMY,PREMIUM_ECONOMY,BUSINESS,FIRST',
            ]);

            $search = $this->duffel->searchFlights($validated);

            $offerRequestId = $search['data']['id'] ?? null;

            if (!$offerRequestId) {
                return response()->json([
                    'error' => 'Failed to create offer request',
                    'response' => $search
                ], 422);
            }

            // Fetch offers in second step
            $offersResponse = $this->duffel->getOffers($offerRequestId);

            $offers = $offersResponse['data'] ?? [];
            $offers = $this->duffel->filterValidOffers($offers, 30);

            return response()->json([
                'offer_request_id' => $offerRequestId,
                'offers' => $offers,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Flight search failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getOffers(Request $request)
    {
        try {
            $offerRequestId = $request->query('offer_request_id');

            if (!$offerRequestId) {
                return response()->json(['error' => 'offer_request_id required'], 422);
            }

            $offers = $this->duffel->getOffers($offerRequestId);

            $items = $offers['data'] ?? [];
            $items = $this->duffel->filterValidOffers($items, 300);

            return response()->json($items);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch offers',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getOffer(string $offerId)
    {
        try {
            $offer = $this->duffel->getOffer($offerId);

            return response()->json($offer);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch offer',
                'message' => $e->getMessage()
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

            // Always fetch latest offer data before booking
            $offerResponse = $this->duffel->getOffer($validated['offer_id']);
            $offer = $offerResponse['data'] ?? null;

            if (!$offer) {
                return response()->json([
                    'error' => 'Offer not found',
                ], 422);
            }

            $payload = [
                'selected_offers' => [$validated['offer_id']],
                'payments' => [
                    [
                        'type' => 'balance',
                        'amount' => $offer['total_amount'],
                        'currency' => $offer['total_currency'],
                    ]
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
        } catch (\Exception $e) {
            $status = str_contains($e->getMessage(), '422') ? 422 : 500;

            return response()->json([
                'error' => 'Order creation failed',
                'message' => $e->getMessage()
            ], $status);
        }
    }

    public function getOrder(string $orderId)
    {
        try {
            $order = $this->duffel->getOrder($orderId);

            return response()->json($order);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch order',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
