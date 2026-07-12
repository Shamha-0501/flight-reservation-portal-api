<x-email-layout
    title="Verify Email"
    heading="Verify Your Email"
    :footer-name="$tenant"
>
    <p>Hello {{ $name }},</p>
    <p>Thank you for registering with <strong>{{ $tenant }}</strong>.</p>
    <p>Please verify your email address by clicking the button below. This verification link is valid for a limited time and can only be used once.</p>

    <x-slot:actions>
        <a href="{{ $verificationUrl }}"
           style="background:#2563eb;color:#ffffff;text-decoration:none;padding:14px 28px;border-radius:8px;display:inline-block;font-weight:bold;">
            Verify Email Address
        </a>
    </x-slot:actions>

    <p>If the button above does not work, copy and paste the following link into your browser:</p>
    <p style="word-break:break-all;">{{ $verificationUrl }}</p>
    <p>If you did not create this account, you can safely ignore this email.</p>
</x-email-layout>
