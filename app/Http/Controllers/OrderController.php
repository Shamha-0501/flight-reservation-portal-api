<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'tenantKey' => ['required', 'string', 'exists:tenants,key'],
            'email' => ['nullable', 'email'],
            'status' => ['nullable', 'string'],
        ]);

        $tenant = Tenant::where('key', $validated['tenantKey'])->firstOrFail();

        $query = Order::query()
            ->where('tenant_id', $tenant->id)
            ->with([
                'user',
                'passengers',
                'addons',
            ])
            ->latest();

        if (! empty($validated['email'])) {
            $query->whereHas('user', function ($q) use ($validated) {
                $q->where('email', $validated['email']);
            });
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        return OrderResource::collection(
            $query->paginate(20)
        );
    }

    public function show(Request $request, Order $order)
    {
        $validated = $request->validate([
            'tenantKey' => ['required', 'string', 'exists:tenants,key'],
        ]);

        $tenant = Tenant::where('key', $validated['tenantKey'])->firstOrFail();

        abort_if($order->tenant_id !== $tenant->id, 404);

        $order->load([
            'user',
            'passengers',
            'addons',
        ]);

        return response()->json([
            'ok' => true,
            'order' => OrderResource::make($order),
        ]);
    }
}