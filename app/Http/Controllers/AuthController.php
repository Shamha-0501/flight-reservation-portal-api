<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Jobs\SendBladeMail;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use App\Services\ActivityLogger;
use App\Services\MailService;
use App\Models\UserAuthToken;

class AuthController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * Cookie-based signup (session auth).
     * Requires valid XSRF token (419 if missing/invalid).
     */
    public function registerTenant(Request $request)
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'email'    => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        [$user, $tenant] = DB::transaction(function () use ($validated) {
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
                'status' => 'active',
                'invited_by_user_id' => $user->id,
            ]);

            return [$user, $tenant];
        });

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        SendBladeMail::dispatch(
            recipientEmail: $user->email,
            subject: 'Welcome to ' . ($tenant->name ?? config('app.name')),
            view: 'emails.tenant-welcome',
            data: [
                'name' => $user->name,
                'tenantName' => $tenant->name ?? config('app.name'),
                'tenantKey' => $tenant->key ?? null,
                'dashboardUrl' => rtrim(config('app.frontend_url'), '/') . '/admin',
            ],
            logLabel: 'tenant welcome'
        );

        return response()->json([
            'ok'   => true,
            'user' => UserResource::make($user->load('tenants')),
        ], 201);
    }

    public function registerCustomer(Request $request)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:190'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['required', 'string', 'max:40'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', 'in:customer'],
            'terms' => ['accepted'],
        ]);

        $user = User::query()
            ->where('email', $validated['email'])
            ->first();

        if ($user) {
            if (
                $user->account_state === 'order_verified_only'
                && $user->name === 'Guest User'
            ) {
                $user->update([
                    'name' => $validated['name'],
                    'password' => Hash::make($validated['password']),
                    'account_state' => 'account',
                ]);
            } else {
                return back()
                    ->withErrors([
                        'email' => 'An account already exists with this email address.',
                    ])
                    ->withInput();
            }
        } else {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'account_state' => 'account',
            ]);
        }


        SendBladeMail::dispatch(
            recipientEmail: $user->email,
            subject: 'Welcome to ' . config('app.name'),
            view: 'emails.customer-welcome',
            data: [
                'name' => $user->name,
                'appName' => config('app.name'),
                'dashboardUrl' => rtrim(config('app.frontend_url'), '/'),
            ],
            logLabel: 'customer welcome'
        );

        return response()->json([
            'ok' => true,
            'message' => 'Customer account created successfully. Redirecting to login...',
            'user' => UserResource::make($user->load('tenants')),
        ], 201);
    }

    public function registerAgency(Request $request)
    {
        $validated = $request->validate([
            'agency_name' => ['required', 'string', 'max:190'],
            'business_email' => ['required', 'email', 'max:190'],
            'business_phone' => ['required', 'string', 'max:40'],
            'country' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'name' => ['required', 'string', 'max:190'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:40'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'agency_logo' => ['nullable', 'image', 'max:4096'],
            'services' => ['nullable', 'array'],
            'services.*' => ['string', 'max:120'],
        ]);

        $logoPath = $request->file('agency_logo')?->store('agency-logos', 'public');

        $result = DB::transaction(function () use ($validated, $logoPath) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'account_state' => 'account',
            ]);

            $tenant = Tenant::create([
                'name' => $validated['agency_name'],
                'status' => 'pending',
                'created_by_user_id' => $user->id,
                'meta' => [
                    'business_email' => $validated['business_email'],
                    'business_phone' => $validated['business_phone'],
                    'country' => $validated['country'],
                    'city' => $validated['city'],
                    'address' => $validated['address'] ?? null,
                    'description' => $validated['description'] ?? null,
                    'contact_phone' => $validated['phone'],
                    'services' => array_values($validated['services'] ?? []),
                    'logo_path' => $logoPath,
                ],
            ]);

            $tenantOwnerRoleId = Role::where('key', 'tenant_owner')->value('id');

            TenantUser::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'role_id' => $tenantOwnerRoleId,
                'invited_by_user_id' => $user->id,
                'status' => 'active',
            ]);

            return [$user, $tenant];
        });

        [$user, $tenant] = $result;

        $this->activityLogger->log(
            action: 'tenant.registration_submitted',
            request: $request,
            tenant: $tenant,
            actor: $user,
            subject: $tenant,
            title: 'Agency registration submitted',
            description: "{$tenant->name} submitted an agency registration request.",
            category: 'tenant',
            properties: [
                'tenant_key' => $tenant->key,
                'status' => $tenant->status,
                'business_email' => $tenant->meta['business_email'] ?? null,
            ],
        );

        SendBladeMail::dispatch(
            recipientEmail: $user->email,
            subject: $tenant->name . ' registration received',
            view: 'emails.agency-registration-pending',
            data: [
                'name' => $user->name,
                'agencyName' => $tenant->name,
                'businessEmail' => $validated['business_email'],
                'dashboardUrl' => rtrim(config('app.frontend_url'), '/') . '/login',
            ],
            logLabel: 'agency registration'
        );

        SendBladeMail::dispatch(
            recipientEmail: config('mail.admin_notification_address'),
            subject: 'New agency registration pending approval',
            view: 'emails.admin-agency-registration-alert',
            data: [
                'agencyName' => $tenant->name,
                'ownerName' => $user->name,
                'ownerEmail' => $user->email,
                'businessEmail' => $validated['business_email'],
                'businessPhone' => $validated['business_phone'],
                'country' => $validated['country'],
                'city' => $validated['city'],
                'address' => $validated['address'] ?? null,
                'description' => $validated['description'] ?? null,
                'tenantKey' => $tenant->key,
                'status' => $tenant->status,
            ],
            logLabel: 'agency registration alert',
            context: [
                'tenant_id' => $tenant->id,
                'tenant_key' => $tenant->key,
            ]
        );

        return response()->json([
            'ok' => true,
            'message' => 'Agency account created. Your workspace is pending approval before dashboard access is enabled.',
            'user' => UserResource::make($user->load('tenants')),
            'tenant' => [
                'id' => $tenant->id,
                'key' => $tenant->key,
                'name' => $tenant->name,
                'role' => 'Tenant Owner',
                'role_key' => 'tenant_owner',
                'status' => $tenant->status,
            ],
        ], 201);
    }


    /**
     * Cookie-based signup (session auth).
     * Requires valid XSRF token (419 if missing/invalid).
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'tenantKey' => ['required', 'string', 'exists:tenants,key'],
            'name'      => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:190'],
            'password'  => ['required', 'confirmed', Password::defaults()],
        ]);

        $tenant =  Tenant::where('key', $validated['tenantKey'])->first();

        $existingUser = User::where('email', $validated['email'])->first();

        if (
            $existingUser &&
            $existingUser->account_state !== 'order_verified_only'
        ) {
            return response()->json([
                'ok' => false,
                'message' => 'This email is already registered.',
                'errors' => [
                    'email' => ['This email is already registered.'],
                ],
            ], 422);
        }

        $user = DB::transaction(function () use ($validated, $tenant, $existingUser) {
            if ($existingUser) {
                $existingUser->forceFill([
                    'name' => $validated['name'],
                    'password' => Hash::make($validated['password']),
                    'account_state' => 'account',
                ])->save();

                $user = $existingUser;
            } else {
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'account_state' => 'account',
                ]);
            }

            $role_id = Role::where('key', 'customer')->value('id');

            TenantUser::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'role_id' => $role_id,
                'status' => 'active',
            ]);

            return $user;
        });

        $plainToken = Str::random(64);

        UserAuthToken::create([
            'user_id' => $user->id,
            'type' => 'email_verification',
            'token' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes(10),
        ]);

        $verificationUrl = config('app.frontend_url') . '/verify/email?token=' . $plainToken . '&email=' . $user->email;

        $htmlBody = view('emails.verify', [
            'name' => $user->name,
            'tenant' => $tenant->name,
            'verificationUrl' => $verificationUrl,
        ])->render();

        $this->dispatchVerificationMail(
            $user->email,
            "$tenant->name Email Verification",
            $htmlBody
        );

        return response()->json([
            'ok' => true,
            'message' => 'Registration successful. Verification email sent.',
            'user' => UserResource::make($user->load('tenants')),
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

    public function verifyEmail(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $hashedToken = hash('sha256', $validated['token']);

        $authToken = UserAuthToken::where('token', $hashedToken)
            ->where('type', 'email_verification')
            ->first();

        if (! $authToken) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid verification token.',
            ], 422);
        }

        if ($authToken->used) {
            return response()->json([
                'ok' => false,
                'message' => 'Verification token already used.',
            ], 422);
        }

        if (now()->greaterThan($authToken->expires_at)) {
            return response()->json([
                'ok' => false,
                'code' => 'TOKEN_EXPIRED',
                'message' => 'Verification token expired. Please request a new verification email.',
            ], 422);
        }

        DB::transaction(function () use ($authToken) {
            $authToken->user->forceFill([
                'email_verified_at' => now(),
            ])->save();

            $authToken->forceFill([
                'used' => true,
                'used_at' => now(),
            ])->save();
        });

        Auth::guard('web')->login($authToken->user);
        $request->session()->regenerate();

        return response()->json([
            'ok' => true,
            'message' => 'Email verified successfully.',
            'user' => UserResource::make($authToken->user->load('tenants')),
        ]);
    }

    public function verifyEmailWithCode(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:8'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid verification request.',
            ], 422);
        }

        $authToken = UserAuthToken::where('user_id', $user->id)
            ->where('type', 'email_verification')
            ->where('used', false)
            ->latest()
            ->first();

        if (! $authToken) {
            return response()->json([
                'ok' => false,
                'message' => 'Verification code not found.',
            ], 422);
        }

        if (now()->greaterThan($authToken->expires_at)) {
            return response()->json([
                'ok' => false,
                'code' => 'TOKEN_EXPIRED',
                'message' => 'Verification code expired.',
            ], 422);
        }

        if (! Hash::check($validated['code'], $authToken->token)) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid verification code.',
            ], 422);
        }

        DB::transaction(function () use ($user, $authToken) {

            $user->forceFill([
                'email_verified_at' => now(),
            ])->save();

            $authToken->forceFill([
                'used' => true,
                'used_at' => now(),
            ])->save();
        });

        return response()->json([
            'ok' => true,
            'verified' => true,
            'message' => 'Email verified successfully.',
        ]);
    }

    public function resendVerificationEmail(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if ($user->email_verified_at !== null) {
            return response()->json([
                'ok' => false,
                'message' => 'Email already verified.',
            ], 422);
        }

        DB::transaction(function () use ($user) {
            UserAuthToken::where('user_id', $user->id)
                ->where('type', 'email_verification')
                ->where('used', false)
                ->update([
                    'used' => true,
                    'used_at' => now(),
                ]);
        });

        $plainToken = Str::random(64);

        UserAuthToken::create([
            'user_id' => $user->id,
            'type' => 'email_verification',
            'token' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes(10),
        ]);

        $tenant = $user->tenants()->first();

        $verificationUrl = config('app.frontend_url') . '/verify/email?token=' . $plainToken . '&email=' . $user->email;

        $htmlBody = view('emails.verify', [
            'name' => $user->name,
            'tenant' => $tenant?->name,
            'verificationUrl' => $verificationUrl,
        ])->render();

        $this->dispatchVerificationMail(
            $user->email,
            ($tenant?->name ?? config('app.name')) . ' Email Verification',
            $htmlBody
        );

        return response()->json([
            'ok' => true,
            'message' => 'New verification email sent.',
            'expires_in' => 600,
        ]);
    }

    public function sendEmailVerificationCode(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'name'  => ['nullable', 'string', 'max:100'],
        ]);

        $existingUser = User::where('email', $validated['email'])->first();
        if ($existingUser && $existingUser->account_state !== 'order_verified_only') {
            return response()->json([
                'ok' => false,
                'requires_login' => true,
                'account_exists' => true,
                'account_state' => $existingUser->account_state,
                'message' => 'This email is already registered. Please sign in to continue booking.',
            ], 409);
        }

        $user = DB::transaction(function () use ($validated) {
            $user = User::where('email', $validated['email'])->first();

            if (! $user) {
                $user = User::create([
                    'name' => $validated['name'] ?? 'Guest User',
                    'email' => $validated['email'],
                    'password' => Hash::make(Str::random(64)),
                    'account_state' => 'order_verified_only',
                    'email_verified_at' => null,
                ]);
            }

            UserAuthToken::where('user_id', $user->id)
                ->where('type', 'email_verification')
                ->where('used', false)
                ->update([
                    'used' => true,
                    'used_at' => now(),
                ]);

            $verificationCode = str_pad(
                (string) random_int(0, 99999999),
                8,
                '0',
                STR_PAD_LEFT
            );

            UserAuthToken::create([
                'user_id' => $user->id,
                'type' => 'email_verification',
                'token' => Hash::make($verificationCode),
                'expires_at' => now()->addMinutes(10),
            ]);

            $user->verification_plain_code = $verificationCode;

            return $user;
        });

        $tenant = $user->tenants()->first();

        $htmlBody = view('emails.vcode', [
            'name' => $user->name,
            'tenant' => $tenant?->name ?? config('app.name'),
            'code' => implode(' ', str_split($user->verification_plain_code, 4)),
        ])->render();

        $this->dispatchVerificationMail(
            $user->email,
            ($tenant?->name ?? config('app.name')) . ' Booking Verification',
            $htmlBody
        );

        return response()->json([
            'ok' => true,
            'message' => 'Verification email sent.',
        ], 201);
    }

    public function emailBookingStatus(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:190'],
        ]);

        $user = User::where('email', $validated['email'])->first();
        $requiresLogin = (bool) ($user && $user->account_state !== 'order_verified_only');

        return response()->json([
            'ok' => true,
            'requires_login' => $requiresLogin,
            'account_exists' => (bool) $user,
            'account_state' => $user?->account_state,
            'message' => $requiresLogin
                ? 'This email is already registered. Please sign in to continue booking.'
                : 'Email can be used for booking verification.',
        ]);
    }

    private function dispatchVerificationMail(string $to, string $subject, string $htmlBody): void
    {
        dispatch(function () use ($to, $subject, $htmlBody) {
            try {
                $result = MailService::sendMail($to, $subject, $htmlBody);

                if (!($result['ok'] ?? false)) {
                    Log::error('Verification mail send failed.', [
                        'to' => $to,
                        'subject' => $subject,
                        'error' => $result['message'] ?? 'Unknown mail error',
                    ]);
                }
            } catch (\Throwable $exception) {
                Log::error('Verification mail dispatch threw an exception.', [
                    'to' => $to,
                    'subject' => $subject,
                    'error' => $exception->getMessage(),
                ]);
            }
        })->afterResponse();
    }
}
