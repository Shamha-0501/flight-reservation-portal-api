<x-email-layout
    title="Agency Registration Update"
    heading="Agency Registration Update"
    subtitle="Your request was not approved at this time."
    :footer-name="$tenantName"
>
    <p>Hello {{ $name ?? 'User' }},</p>
    <p>We appreciate your interest in joining <strong>{{ $tenantName }}</strong>.</p>
    <p>After review, the agency registration could not be approved at this time. If you believe this was a mistake or if additional details are needed, please contact the support team and reference workspace key <strong>{{ $tenantKey }}</strong>.</p>

    <x-slot:actions>
        <a href="{{ $dashboardUrl }}"
           style="background:#2563eb;color:#ffffff;text-decoration:none;padding:14px 28px;border-radius:8px;display:inline-block;font-weight:bold;">
            Return to Login
        </a>
    </x-slot:actions>

    <p>If you need help, please reply to this email or contact support through the platform.</p>
</x-email-layout>
