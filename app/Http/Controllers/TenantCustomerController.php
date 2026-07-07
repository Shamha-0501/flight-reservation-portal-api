<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TenantCustomerController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'tenantKey' => ['required', 'string', 'exists:tenants,key'],
            'search' => ['nullable', 'string', 'max:190'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $tenant = Tenant::where('key', $validated['tenantKey'])->firstOrFail();
        $perPage = (int) ($validated['per_page'] ?? 20);
        $search = trim((string) ($validated['search'] ?? ''));

        $customers = User::query()
            ->select('users.id', 'users.name', 'users.email')
            ->join('orders', 'orders.user_id', '=', 'users.id')
            ->leftJoin('passengers', function ($join) use ($tenant) {
                $join->on('passengers.order_id', '=', 'orders.id')
                    ->where('passengers.tenant_id', '=', $tenant->id)
                    ->whereNull('passengers.deleted_at');
            })
            ->where('orders.tenant_id', $tenant->id)
            ->whereNull('orders.deleted_at')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested
                        ->where('users.name', 'like', "%{$search}%")
                        ->orWhere('users.email', 'like', "%{$search}%")
                        ->orWhere('passengers.phone_number', 'like', "%{$search}%")
                        ->orWhere('orders.booking_reference', 'like', "%{$search}%");
                });
            })
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc(DB::raw('MAX(orders.created_at)'))
            ->paginate($perPage);

        $customers->getCollection()->transform(function (User $user) use ($tenant) {
            $orderStats = DB::table('orders')
                ->where('tenant_id', $tenant->id)
                ->where('user_id', $user->id)
                ->whereNull('deleted_at')
                ->selectRaw('COUNT(*) as total_bookings')
                ->selectRaw("SUM(CASE WHEN cancellation_status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_bookings")
                ->selectRaw("SUM(CASE WHEN cancellation_status != 'Cancelled' THEN 1 ELSE 0 END) as active_bookings")
                ->selectRaw("SUM(CASE WHEN refund_status = 'Refund Pending' THEN 1 ELSE 0 END) as refund_pending_bookings")
                ->selectRaw('MAX(created_at) as last_booking_at')
                ->first();

            $phone = DB::table('passengers')
                ->join('orders', 'orders.id', '=', 'passengers.order_id')
                ->where('orders.tenant_id', $tenant->id)
                ->where('orders.user_id', $user->id)
                ->whereNull('orders.deleted_at')
                ->whereNull('passengers.deleted_at')
                ->whereNotNull('passengers.phone_number')
                ->orderByDesc('orders.created_at')
                ->value('passengers.phone_number');

            $status = ((int) ($orderStats->total_bookings ?? 0)) >= 10
                ? 'VIP'
                : (((int) ($orderStats->refund_pending_bookings ?? 0)) > 0 ? 'Watchlist' : 'Active');

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $phone,
                'total_bookings' => (int) ($orderStats->total_bookings ?? 0),
                'active_bookings' => (int) ($orderStats->active_bookings ?? 0),
                'cancelled_bookings' => (int) ($orderStats->cancelled_bookings ?? 0),
                'refund_pending_bookings' => (int) ($orderStats->refund_pending_bookings ?? 0),
                'status' => $status,
                'last_booking_at' => $orderStats->last_booking_at,
            ];
        });

        return response()->json($customers);
    }
}
