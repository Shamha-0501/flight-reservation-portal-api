<?php

namespace App\Http\Controllers;

use App\Http\Resources\TenantInvitationResource;
use App\Http\Resources\TenantMemberResource;
use App\Http\Resources\UserResource;
use App\Jobs\SendBladeMail;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class TenantMemberController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'tenantKey' => ['required', 'string', 'exists:tenants,key'],
        ]);

        $tenant = Tenant::where('key', $validated['tenantKey'])->firstOrFail();

        $members = TenantUser::query()
            ->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->with(['user', 'role', 'inviter'])
            ->latest()
            ->get();

        $invites = TenantInvitation::query()
            ->where('tenant_id', $tenant->id)
            ->with(['role', 'inviter'])
            ->latest()
            ->get();

        return response()->json([
            'ok' => true,
            'data' => [
                'members' => TenantMemberResource::collection($members),
                'invites' => TenantInvitationResource::collection($invites),
            ],
        ]);
    }

    public function invite(Request $request)
    {
        $validated = $request->validate([
            'tenantKey' => ['required', 'string', 'exists:tenants,key'],
            'email' => ['required', 'email', 'max:190'],
            'role_key' => ['required', Rule::in([
                'tenant_owner',
                'tenant_admin',
                'agency_manager',
                'agency_staff',
            ])],
        ]);

        $tenant = Tenant::where('key', $validated['tenantKey'])->firstOrFail();
        $role = Role::where('key', $validated['role_key'])->firstOrFail();

        $existingUser = User::where('email', $validated['email'])->first();
        $existingMembership = $existingUser
            ? TenantUser::where('tenant_id', $tenant->id)
                ->where('user_id', $existingUser->id)
                ->whereNull('deleted_at')
                ->first()
            : null;

        if ($existingMembership) {
            $existingMembership->forceFill([
                'role_id' => $role->id,
                'status' => 'active',
                'invited_by_user_id' => $request->user()?->id,
            ])->save();

            $this->activityLogger->log(
                action: 'tenant.member_role_updated',
                request: $request,
                tenant: $tenant,
                actor: $request->user(),
                subject: $existingMembership,
                title: 'Tenant member role updated',
                description: "{$validated['email']} already belonged to {$tenant->name}; the role was updated.",
                category: 'tenant_member',
                properties: [
                    'email' => $validated['email'],
                    'role_key' => $validated['role_key'],
                ],
            );

            return response()->json([
                'ok' => true,
                'message' => 'Member already belongs to this tenant. Role updated.',
                'member' => TenantMemberResource::make($existingMembership->load(['user', 'role', 'inviter'])),
            ]);
        }

        $plainToken = Str::random(64);
        $invitation = TenantInvitation::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'email' => $validated['email'],
                'status' => 'pending',
            ],
            [
                'role_id' => $role->id,
                'token' => hash('sha256', $plainToken),
                'invited_by_user_id' => $request->user()?->id,
                'expires_at' => now()->addDays(7),
                'accepted_at' => null,
                'revoked_at' => null,
            ]
        );

        $this->dispatchInvitationMail($tenant, $invitation, $plainToken);

        $this->activityLogger->log(
            action: 'tenant.member_invited',
            request: $request,
            tenant: $tenant,
            actor: $request->user(),
            subject: $invitation,
            title: 'Tenant invitation sent',
            description: "{$validated['email']} was invited to {$tenant->name}.",
            category: 'tenant_member',
            properties: [
                'email' => $validated['email'],
                'role_key' => $validated['role_key'],
                'invitation_id' => $invitation->id,
            ],
        );

        return response()->json([
            'ok' => true,
            'message' => 'Tenant invitation created successfully.',
            'invitation' => TenantInvitationResource::make($invitation->load(['role', 'inviter'])),
            'invite_url' => $this->invitationUrl($plainToken, $validated['email']),
        ], 201);
    }

    public function resendInvite(Request $request, TenantInvitation $invitation)
    {
        $request->validate([
            'tenantKey' => ['required', 'string', 'exists:tenants,key'],
        ]);

        $tenant = Tenant::where('key', $request->input('tenantKey'))->firstOrFail();
        abort_if($invitation->tenant_id !== $tenant->id, 404);
        abort_if($invitation->status !== 'pending', 422, 'Invitation is no longer pending.');

        $plainToken = Str::random(64);
        $invitation->forceFill([
            'token' => hash('sha256', $plainToken),
            'status' => 'pending',
            'accepted_at' => null,
            'revoked_at' => null,
            'expires_at' => now()->addDays(7),
        ])->save();

        $this->dispatchInvitationMail($tenant, $invitation, $plainToken);

        $this->activityLogger->log(
            action: 'tenant.invitation_resent',
            request: $request,
            tenant: $tenant,
            actor: $request->user(),
            subject: $invitation,
            title: 'Tenant invitation resent',
            description: "An invitation for {$invitation->email} was resent.",
            category: 'tenant_member',
            properties: [
                'email' => $invitation->email,
                'invitation_id' => $invitation->id,
            ],
        );

        return response()->json([
            'ok' => true,
            'message' => 'Invitation resent successfully.',
            'invitation' => TenantInvitationResource::make($invitation->load(['role', 'inviter'])),
            'invite_url' => $this->invitationUrl($plainToken, $invitation->email),
        ]);
    }

    public function acceptInvitation(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:190'],
            'email' => ['nullable', 'email', 'max:190'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        $hashedToken = hash('sha256', $validated['token']);
        $invitation = TenantInvitation::where('token', $hashedToken)
            ->where('status', 'pending')
            ->first();

        if (! $invitation) {
            return response()->json([
                'ok' => false,
                'message' => 'This invitation link is invalid or has already been replaced. Ask the tenant admin to resend the invitation.',
            ], 422);
        }

        abort_if($invitation->isPending() === false, 422, 'Invitation is no longer valid.');

        $user = $request->user();

        if ($user) {
            abort_if($user->email !== $invitation->email, 422, 'Invitation email does not match the signed-in account.');
        } else {
            $guestData = $request->validate([
                'name' => ['required', 'string', 'max:190'],
                'email' => ['required', 'email', 'max:190'],
                'password' => ['required', 'confirmed', Password::defaults()],
            ]);

            abort_if($guestData['email'] !== $invitation->email, 422, 'Invitation email mismatch.');

            $user = User::firstOrCreate(
                ['email' => $guestData['email']],
                [
                    'name' => $guestData['name'],
                    'password' => Hash::make($guestData['password']),
                    'account_state' => 'account',
                ]
            );

            Auth::guard('web')->login($user);
            $request->session()->regenerate();
        }

        $membership = TenantUser::withTrashed()->firstOrNew([
            'tenant_id' => $invitation->tenant_id,
            'user_id' => $user->id,
        ]);

        $membership->forceFill([
            'role_id' => $invitation->role_id,
            'status' => 'active',
            'invited_by_user_id' => $invitation->invited_by_user_id,
            'deleted_at' => null,
        ])->save();

        $invitation->forceFill([
            'status' => 'accepted',
            'accepted_at' => now(),
        ])->save();

        SendBladeMail::dispatch(
            recipientEmail: $user->email,
            subject: 'Welcome to ' . ($membership->tenant?->name ?? config('app.name')),
            view: 'emails.tenant-member-welcome',
            data: [
                'name' => $user->name,
                'tenantName' => $membership->tenant?->name ?? config('app.name'),
                'roleName' => $invitation->role?->name ?? $invitation->role?->key ?? 'Member',
                'dashboardUrl' => rtrim(config('app.frontend_url'), '/') . '/admin',
            ],
            logLabel: 'tenant member welcome',
            context: [
                'tenant_id' => $membership->tenant_id,
                'tenant_user_id' => $membership->id,
                'invitation_id' => $invitation->id,
            ]
        );

        $this->activityLogger->log(
            action: 'tenant.invitation_accepted',
            request: $request,
            tenant: $membership->tenant,
            actor: $user,
            subject: $membership,
            title: 'Tenant invitation accepted',
            description: "{$user->email} accepted a tenant invitation.",
            category: 'tenant_member',
            properties: [
                'email' => $user->email,
                'tenant_id' => $membership->tenant_id,
                'tenant_user_id' => $membership->id,
            ],
        );

        return response()->json([
            'ok' => true,
            'user' => UserResource::make($user->load('tenants')),
            'member' => TenantMemberResource::make($membership->load(['user', 'role', 'inviter'])),
        ]);
    }

    public function changeRole(Request $request, TenantUser $member)
    {
        $validated = $request->validate([
            'tenantKey' => ['required', 'string', 'exists:tenants,key'],
            'role_key' => ['required', Rule::in([
                'tenant_owner',
                'tenant_admin',
                'agency_manager',
                'agency_staff',
            ])],
        ]);

        $tenant = Tenant::where('key', $validated['tenantKey'])->firstOrFail();
        abort_if($member->tenant_id !== $tenant->id, 404);

        $role = Role::where('key', $validated['role_key'])->firstOrFail();
        $member->forceFill([
            'role_id' => $role->id,
            'status' => 'active',
        ])->save();

        $this->activityLogger->log(
            action: 'tenant.member_role_changed',
            request: $request,
            tenant: $tenant,
            actor: $request->user(),
            subject: $member,
            title: 'Tenant member role changed',
            description: "A member role was changed inside {$tenant->name}.",
            category: 'tenant_member',
            properties: [
                'member_id' => $member->id,
                'role_key' => $validated['role_key'],
                'user_id' => $member->user_id,
            ],
        );

        return response()->json([
            'ok' => true,
            'member' => TenantMemberResource::make($member->load(['user', 'role', 'inviter'])),
        ]);
    }

    public function remove(Request $request, TenantUser $member)
    {
        $validated = $request->validate([
            'tenantKey' => ['required', 'string', 'exists:tenants,key'],
        ]);

        $tenant = Tenant::where('key', $validated['tenantKey'])->firstOrFail();
        abort_if($member->tenant_id !== $tenant->id, 404);

        $member->forceFill([
            'status' => 'inactive',
        ])->save();
        $member->delete();

        $this->activityLogger->log(
            action: 'tenant.member_removed',
            request: $request,
            tenant: $tenant,
            actor: $request->user(),
            subject: $member,
            title: 'Tenant member removed',
            description: "A member was removed from {$tenant->name}.",
            category: 'tenant_member',
            properties: [
                'member_id' => $member->id,
                'user_id' => $member->user_id,
            ],
        );

        return response()->noContent();
    }

    private function dispatchInvitationMail(Tenant $tenant, TenantInvitation $invitation, string $plainToken): void
    {
        SendBladeMail::dispatch(
            recipientEmail: $invitation->email,
            subject: "{$tenant->name} workspace invitation",
            view: 'emails.tenant-invitation',
            data: [
                'name' => $invitation->email,
                'tenantName' => $tenant->name,
                'roleName' => $invitation->role?->name ?? $invitation->role?->key ?? 'Member',
                'inviteUrl' => $this->invitationUrl($plainToken, $invitation->email),
                'expiresAt' => $invitation->expires_at?->format('M d, Y h:i A'),
            ],
            logLabel: 'tenant invitation',
            context: [
                'tenant_id' => $tenant->id,
                'invitation_id' => $invitation->id,
            ]
        );
    }

    private function invitationUrl(string $token, string $email): string
    {
        return rtrim(config('app.frontend_url'), '/') . '/admin/users/invite?token=' . urlencode($token) . '&email=' . urlencode($email);
    }
}
