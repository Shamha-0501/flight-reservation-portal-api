<x-email-layout
    title="Welcome"
    heading="Welcome to {{ $appName }}"
    :footer-name="$appName"
>
    <p>Hello {{ $name ?? 'Customer' }},</p>
    <p>Your account has been created successfully. You can now sign in and start using {{ $appName }}.</p>
    <p>Use the dashboard below to continue with your booking journey and manage your account details.</p>

    <x-slot:actions>
        <a href="{{ $dashboardUrl }}"
           style="background:#2563eb;color:#ffffff;text-decoration:none;padding:14px 28px;border-radius:8px;display:inline-block;font-weight:bold;">
            Go to Dashboard
        </a>
    </x-slot:actions>

    <p>If the button above does not work, open this link:</p>
    <p style="word-break:break-all;">{{ $dashboardUrl }}</p>
</x-email-layout>
