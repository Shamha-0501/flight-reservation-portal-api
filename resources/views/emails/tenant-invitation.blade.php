<x-email-layout
    title="Workspace Invitation"
    heading="Workspace Invitation"
    subtitle="You have been invited to join a tenant workspace."
    :footer-name="$tenantName"
>
    <p>Hello {{ $name ?? 'there' }},</p>
    <p>You have been invited to join <strong>{{ $tenantName }}</strong> as <strong>{{ $roleName }}</strong>.</p>
    <p>Please accept the invitation below to complete your workspace access.</p>

    <x-slot:actions>
        <a href="{{ $inviteUrl }}"
           style="background:#2563eb;color:#ffffff;text-decoration:none;padding:14px 28px;border-radius:8px;display:inline-block;font-weight:bold;">
            Accept Invitation
        </a>
    </x-slot:actions>

    <p>If the button above does not work, use this link:</p>
    <p style="word-break:break-all;">{{ $inviteUrl }}</p>
    @if(!empty($expiresAt))
        <p>This invitation is valid until <strong>{{ $expiresAt }}</strong>.</p>
    @endif
</x-email-layout>
