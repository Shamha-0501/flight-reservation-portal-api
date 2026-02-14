<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FlightSearchController extends Controller
{
    public function searchFlightOffers(Request $request)
    {
        $validated = $request->validate([
            'originLocationCode' => 'required|string|max:10',
            'destinationLocationCode' => 'required|string|max:10',
            'departureDate' => 'required|date_format:Y-m-d',
            'returnDate' => 'nullable|date_format:Y-m-d',
            'adults' => 'required|integer|min:1',
            'children' => 'required|numeric',
            'infants' => 'required|numeric',
            'travelClass' => 'required|string'
        ]);

        $results = $this->getFlightOffers(
            $validated['originLocationCode'],
            $validated['destinationLocationCode'],
            $validated['departureDate'],
            $validated['returnDate'],
            $validated['adults'],
            $validated['children'],
            $validated['infants'],
            $validated['travelClass']
        );

        return response()->json([
            'data' => $results,
        ], 200);
    }
}
