<x-email-layout
    title="Flight Booking Verification"
    heading="Confirm Your Booking"
    :footer-name="$tenant"
>
    <p>Hello {{ $name }},</p>
    <p>We received a request to continue your flight booking with <strong>{{ $tenant }}</strong>.</p>
    <p>Please use the verification code below to confirm your booking request.</p>

    <x-slot:details>
        <table width="100%" cellpadding="0" cellspacing="0" style="text-align:center;">
            <tr>
                <td>
                    <div style="display:inline-block;background:#2563eb;color:#ffffff;font-size:32px;font-weight:bold;letter-spacing:8px;padding:18px 32px;border-radius:12px;">
                        {{ $code }}
                    </div>
                </td>
            </tr>
        </table>
    </x-slot:details>

    <p>This verification code will expire in <strong>10 minutes</strong>.</p>
    <p>For your security, do not share this code with anyone.</p>
    <p>If you did not request this booking verification, you can safely ignore this email.</p>
</x-email-layout>
