<x-email-layout
    title="Welcome"
    heading="Welcome to {{ $tenantName }}"
    subtitle="Your workspace access is now active."
    :footer-name="$tenantName"
>
    <p>Hello {{ $name ?? 'User' }},</p>
    <p>You are now a <strong>{{ $roleName }}</strong> in <strong>{{ $tenantName }}</strong>.</p>
    <p>You can sign in and continue managing the workspace from the dashboard below.</p>

    <x-slot:actions>
        <a href="{{ $dashboardUrl }}"
           style="background:#2563eb;color:#ffffff;text-decoration:none;padding:14px 28px;border-radius:8px;display:inline-block;font-weight:bold;">
            Go to Dashboard
        </a>
    </x-slot:actions>

    <p>If the button above does not work, open this link:</p>
    <p style="word-break:break-all;">{{ $dashboardUrl }}</p>
</x-email-layout>
