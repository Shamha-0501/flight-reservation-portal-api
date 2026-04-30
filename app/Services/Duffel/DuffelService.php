<?php

namespace App\Services\Duffel;

use Illuminate\Support\Carbon;

class DuffelService
{
    protected DuffelClient $client;
    public function __construct()
    {
        $this->client = new DuffelClient();
    }

    public function getPlaceSuggestions(string $query, ?float $lat = null, ?float $lng = null, ?int $radius = null): array
    {
        $params = ['query' => $query];

        if ($lat !== null && $lng !== null && $radius !== null) {
            $params['lat'] = $lat;
            $params['lng'] = $lng;
            $params['rad'] = $radius;
        }

        return $this->client->get('/places/suggestions', $params);
    }

    public function getAirports(array $params = []): array
    {
        return $this->client->get('/airports', $params);
    }

    public function createOfferRequest(
        array $payload,
        bool $returnOffers = false,
        ?int $supplierTimeout = null
    ): array {
        $query = [
            'return_offers' => $returnOffers ? 'true' : 'false',
        ];

        if ($supplierTimeout !== null) {
            $query['supplier_timeout'] = $supplierTimeout;
        }

        return $this->client->post('/air/offer_requests?' . http_build_query($query), [
            'data' => $payload,
        ]);
    }

    public function searchFlights(array $input): array
    {
        $origin = strtoupper($input['originLocationCode']);
        $destination = strtoupper($input['destinationLocationCode']);
        $departureDate = $input['departureDate'];
        $returnDate = $input['returnDate'] ?? null;
        $travelClass = strtolower($input['travelClass'] ?? 'economy');

        $passengers = [];

        for ($i = 0; $i < ($input['adults'] ?? 0); $i++) {
            $passengers[] = ['type' => 'adult'];
        }

        // Better: accept ages if available
        foreach (($input['childAges'] ?? []) as $age) {
            $passengers[] = ['age' => (int) $age];
        }

        foreach (($input['infantAges'] ?? []) as $age) {
            $passengers[] = ['age' => (int) $age];
        }

        // Fallback if you still only receive counts
        for ($i = 0; $i < ($input['children'] ?? 0) - count($input['childAges'] ?? []); $i++) {
            $passengers[] = ['type' => 'child'];
        }

        for ($i = 0; $i < ($input['infants'] ?? 0) - count($input['infantAges'] ?? []); $i++) {
            $passengers[] = ['type' => 'infant_without_seat'];
        }

        $slices = [
            [
                'origin' => $origin,
                'destination' => $destination,
                'departure_date' => $departureDate,
            ]
        ];

        if (!empty($input['outDepartMin']) || !empty($input['outDepartMax'])) {
            $slices[0]['departure_time'] = array_filter([
                'from' => $this->minutesToTime($input['outDepartMin'] ?? null),
                'to'   => $this->minutesToTime($input['outDepartMax'] ?? null),
            ]);
        }

        if (!empty($input['outArriveMin']) || !empty($input['outArriveMax'])) {
            $slices[0]['arrival_time'] = array_filter([
                'from' => $this->minutesToTime($input['outArriveMin'] ?? null),
                'to'   => $this->minutesToTime($input['outArriveMax'] ?? null),
            ]);
        }

        if ($returnDate) {
            $inboundSlice = [
                'origin' => $destination,
                'destination' => $origin,
                'departure_date' => $returnDate,
            ];

            if (!empty($input['inDepartMin']) || !empty($input['inDepartMax'])) {
                $inboundSlice['departure_time'] = array_filter([
                    'from' => $this->minutesToTime($input['inDepartMin'] ?? null),
                    'to'   => $this->minutesToTime($input['inDepartMax'] ?? null),
                ]);
            }

            if (!empty($input['inArriveMin']) || !empty($input['inArriveMax'])) {
                $inboundSlice['arrival_time'] = array_filter([
                    'from' => $this->minutesToTime($input['inArriveMin'] ?? null),
                    'to'   => $this->minutesToTime($input['inArriveMax'] ?? null),
                ]);
            }

            $slices[] = $inboundSlice;
        }

        $payload = [
            'slices' => $slices,
            'passengers' => $passengers,
            'cabin_class' => $travelClass,
        ];

        // stops: ["0","1"] => use max_connections = max(stops)
        if (!empty($input['stops']) && is_array($input['stops'])) {
            $maxConnections = max(array_map('intval', $input['stops']));
            $payload['max_connections'] = $maxConnections;
        }

        return $this->createOfferRequest(
            payload: $payload,
            returnOffers: true,
            supplierTimeout: $input['supplierTimeout'] ?? 60000
        );
    }

    public function getOffers(
        string $offerRequestId,
        ?int $limit = null,
        ?string $sort = null,
        ?int $maxConnections = null
    ): array {
        $params = [
            'offer_request_id' => $offerRequestId,
        ];

        if ($limit !== null) {
            $params['limit'] = $limit;
        }

        if ($sort !== null) {
            $params['sort'] = $sort;
        }

        if ($maxConnections !== null) {
            $params['max_connections'] = $maxConnections;
        }

        return $this->client->get('/air/offers', $params);
    }

    public function getOffer(string $offerId): array
    {
        return $this->client->get("/air/offers/{$offerId}");
    }

    public function createOrder(array $payload): array
    {
        return $this->client->post('/air/orders', [
            'data' => $payload,
        ]);
    }

    public function getOrder(string $orderId): array
    {
        return $this->client->get("/air/orders/{$orderId}");
    }

    public function listOrders(array $params = []): array
    {
        return $this->client->get('/air/orders', $params);
    }

    public function updateOrder(string $orderId, array $payload): array
    {
        return $this->client->patch("/air/orders/{$orderId}", [
            'data' => $payload,
        ]);
    }

    public function getAvailableServices(string $orderId): array
    {
        return $this->client->get("/air/orders/{$orderId}/available_services");
    }

    public function addServiceToOrder(string $orderId, array $services): array
    {
        return $this->client->post("/air/orders/{$orderId}/services", [
            'data' => [
                'services' => $services,
            ],
        ]);
    }

    public function createOrderCancellation(string $orderId): array
    {
        return $this->client->post('/air/order_cancellations', [
            'data' => [
                'order_id' => $orderId,
            ],
        ]);
    }

    public function getOrderCancellation(string $cancellationId): array
    {
        return $this->client->get("/air/order_cancellations/{$cancellationId}");
    }

    public function confirmOrderCancellation(string $cancellationId): array
    {
        return $this->client->post("/air/order_cancellations/{$cancellationId}/actions/confirm");
    }

    public function createOrderChangeRequest(array $payload): array
    {
        return $this->client->post('/air/order_change_requests', [
            'data' => $payload,
        ]);
    }

    public function getOrderChangeRequest(string $orderChangeRequestId): array
    {
        return $this->client->get("/air/order_change_requests/{$orderChangeRequestId}");
    }

    public function getOrderChangeOffer(string $orderChangeOfferId): array
    {
        return $this->client->get("/air/order_change_offers/{$orderChangeOfferId}");
    }

    public function createOrderChange(array $payload): array
    {
        return $this->client->post('/air/order_changes', [
            'data' => $payload,
        ]);
    }

    public function getOrderChange(string $orderChangeId): array
    {
        return $this->client->get("/air/order_changes/{$orderChangeId}");
    }

    public function confirmOrderChange(string $orderChangeId, array $payment = []): array
    {
        $body = empty($payment) ? [] : [
            'data' => [
                'payment' => $payment,
            ],
        ];

        return $this->client->post("/air/order_changes/{$orderChangeId}/actions/confirm", $body);
    }

    public function getSeatMaps(string $offerId): array
    {
        return $this->client->get('/air/seat_maps', [
            'offer_id' => $offerId,
        ]);
    }

    public function createPaymentIntent(array $payload): array
    {
        return $this->client->post('/payments/payment_intents', [
            'data' => $payload,
        ]);
    }

    public function getPaymentIntent(string $paymentIntentId): array
    {
        return $this->client->get("/payments/payment_intents/{$paymentIntentId}");
    }

    public function confirmPaymentIntent(string $paymentIntentId): array
    {
        return $this->client->post(
            "/payments/payment_intents/{$paymentIntentId}/actions/confirm"
        );
    }

    private function minutesToTime(?int $minutes): ?string
    {
        if ($minutes === null) {
            return null;
        }

        $hours = floor($minutes / 60);
        $mins = $minutes % 60;

        return sprintf('%02d:%02d', $hours, $mins);
    }
}
