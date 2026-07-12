<x-email-layout
    title="Workspace Welcome"
    heading="Welcome to {{ $tenantName }}"
    subtitle="Your workspace is ready."
    :footer-name="$tenantName"
>
    <p>Hello {{ $name ?? 'User' }},</p>
    <p>Your tenant workspace has been created successfully. You can now access your admin area and start managing flights, users, and bookings.</p>
    <p>Please keep your workspace key for reference: <strong>{{ $tenantKey }}</strong>.</p>

    <x-slot:actions>
        <a href="{{ $dashboardUrl }}"
           style="background:#2563eb;color:#ffffff;text-decoration:none;padding:14px 28px;border-radius:8px;display:inline-block;font-weight:bold;">
            Open Workspace
        </a>
    </x-slot:actions>

    <p>If the button above does not work, open this link:</p>
    <p style="word-break:break-all;">{{ $dashboardUrl }}</p>
</x-email-layout>
