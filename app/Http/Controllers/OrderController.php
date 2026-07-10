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
            'tenantKey' => ['nullable', 'string', 'exists:tenants,key'],
            'email' => ['nullable', 'email'],
            'search' => ['nullable', 'string', 'max:190'],
            'status' => ['nullable', 'string'],
            'cancellation_status' => ['nullable', 'string'],
            'refund_status' => ['nullable', 'string'],
            'cancellation_scope' => ['nullable', 'in:all'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Order::query()
            ->with([
                'user',
                'passengers',
                'addons',
                'tenant',
            ])
            ->latest();

        if (! empty($validated['tenantKey'])) {
            $tenant = Tenant::where('key', $validated['tenantKey'])->firstOrFail();
            $query->where('tenant_id', $tenant->id);
        } elseif ($request->user()) {
            $query->where('user_id', $request->user()->id);
        } elseif (! empty($validated['email'])) {
            $query->whereHas('user', function ($q) use ($validated) {
                $q->where('email', $validated['email']);
            });
        } else {
            return response()->json([
                'message' => 'An authenticated customer account or tenant key is required.',
                'errors' => [
                    'tenantKey' => ['An authenticated customer account or tenant key is required.'],
                ],
            ], 422);
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];

            $query->where(function ($q) use ($search) {
                $q->where('booking_reference', 'like', "%{$search}%")
                    ->orWhere('duffel_order_id', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('passengers', function ($passengerQuery) use ($search) {
                        $passengerQuery
                            ->where('given_name', 'like', "%{$search}%")
                            ->orWhere('family_name', 'like', "%{$search}%")
                            ->orWhere('phone_number', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['cancellation_status'])) {
            $query->where('cancellation_status', $validated['cancellation_status']);
        }

        if (! empty($validated['refund_status'])) {
            $query->where('refund_status', $validated['refund_status']);
        }

        if (($validated['cancellation_scope'] ?? null) === 'all') {
            $query->where(function ($q) {
                $q->where('cancellation_status', '!=', Order::CANCELLATION_STATUS_NONE)
                    ->orWhereNotNull('refund_status');
            });
        }

        return OrderResource::collection(
            $query->paginate((int) ($validated['per_page'] ?? 20))
        );
    }

    public function show(Request $request, Order $order)
    {
        $validated = $request->validate([
            'tenantKey' => ['nullable', 'string', 'exists:tenants,key'],
        ]);

        if (! empty($validated['tenantKey'])) {
            $tenant = Tenant::where('key', $validated['tenantKey'])->firstOrFail();
            abort_if($order->tenant_id !== $tenant->id, 404);
        } elseif ($request->user()) {
            abort_if($order->user_id !== $request->user()->id, 404);
        } else {
            abort_if(
                ! empty($request->query('email')) &&
                optional($order->user)->email !== $request->query('email'),
                404
            );
        }

        $order->load([
            'user',
            'passengers',
            'addons',
            'tenant',
        ]);

        return response()->json([
            'ok' => true,
            'order' => OrderResource::make($order),
        ]);
    }
}
