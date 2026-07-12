<x-email-layout
    title="Registration Pending"
    heading="Agency Registration Received"
    subtitle="Your request is waiting for admin approval."
    :footer-name="$agencyName"
>
    <p>Hello {{ $name ?? 'User' }},</p>
    <p>Thank you for registering <strong>{{ $agencyName }}</strong>.</p>
    <p>We have received your agency application and it is now under review. Once an admin approves the workspace, you will be able to continue with the next steps.</p>
    <p>For follow-up communication, we will use <strong>{{ $businessEmail }}</strong>.</p>

    <x-slot:actions>
        <a href="{{ $dashboardUrl }}"
           style="background:#2563eb;color:#ffffff;text-decoration:none;padding:14px 28px;border-radius:8px;display:inline-block;font-weight:bold;">
            Back to Login
        </a>
    </x-slot:actions>

    <p>We appreciate your patience while the request is reviewed.</p>
</x-email-layout>
