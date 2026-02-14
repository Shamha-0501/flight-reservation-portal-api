<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Carbon\Carbon;

class AuthController extends Controller
{
    /**
     * Cookie-based signup (session auth).
     * Requires valid XSRF token (419 if missing/invalid).
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'email'    => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            // Create initial tenant with random 8-char key/name
            $tenant = Tenant::create([
                'name' => $this->uniqueTenantCode(8),
                'key' => $this->uniqueTenantCode(8),
                'created_by_user_id' => $user->id,
            ]);

            $tenant->trial_ends_at = now()->addMonthsNoOverflow(6);
            $tenant->save();

            // Attach user to tenant
            $tenant->users()->attach($user->id, [
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
                'invited_by_user_id' => $user->id,
            ]);

            return $user;
        });

        // Log in using the session (cookie)
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json([
            'ok'   => true,
            'user' => UserResource::make($request->user()->load('tenants')),
        ], 201);
    }

    /**
     * Cookie-based signin (session auth).
     * Requires valid XSRF token (419 if missing/invalid).
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $remember = (bool)($credentials['remember'] ?? false);

        if (! Auth::guard('web')->attempt([
            'email'    => $credentials['email'],
            'password' => $credentials['password'],
        ], $remember)) {
            return response()->json([
                'ok'     => false,
                'errors' => ['email' => ['Invalid credentials.']],
            ], 422);
        }

        // Prevent session fixation
        $request->session()->regenerate();

        return response()->json([
            'ok'   => true,
            'user' => UserResource::make($request->user()->load('tenants')),
        ]);
    }

    /**
     * Logout (session cookie).
     * Requires valid XSRF token (POST).
     */
    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    /**
     * Return the currently authenticated user.
     */
    public function me(Request $request)
    {
        return response()->json([
            'ok'   => true,
            'user' => UserResource::make($request->user()->load('tenants')),
        ]);
    }

    /**
     * Generate a unique random tenant code
     */
    private function uniqueTenantCode(int $length = 8): string 
    {
        do {
            $code = Str::lower(Str::random($length));
        } while (
            Tenant::where('key', $code)->orWhere('name', $code)->exists()
        );

        return $code;
    }
}
