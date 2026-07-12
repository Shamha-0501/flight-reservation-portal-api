<x-email-layout
    title="Agency Approved"
    heading="Your Agency Has Been Approved"
    subtitle="Workspace access is now active."
    :footer-name="$tenantName"
>
    <p>Hello {{ $name ?? 'User' }},</p>
    <p>Good news. Your agency <strong>{{ $tenantName }}</strong> has been approved by the admin team.</p>
    <p>You can now sign in, access your workspace, and continue managing your travel operations.</p>
    <p>Your workspace key is <strong>{{ $tenantKey }}</strong>.</p>

    <x-slot:actions>
        <a href="{{ $dashboardUrl }}"
           style="background:#2563eb;color:#ffffff;text-decoration:none;padding:14px 28px;border-radius:8px;display:inline-block;font-weight:bold;">
            Open Workspace
        </a>
    </x-slot:actions>
</x-email-layout>
