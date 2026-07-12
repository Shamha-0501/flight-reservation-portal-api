<x-email-layout
    title="Cancellation Confirmed"
    heading="Cancellation Confirmed"
    subtitle="Your booking cancellation was completed successfully."
    :footer-name="$tenantName"
>
    <p>Hello {{ $name ?? 'Customer' }},</p>
    <p>Your booking with <strong>{{ $tenantName }}</strong> has been cancelled.</p>

    <x-slot:details>
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border-radius:10px;padding:20px;">
            <tr><td style="color:#6b7280;font-size:14px;">Booking Reference</td><td align="right" style="color:#111827;font-weight:bold;">{{ $bookingReference }}</td></tr>
            <tr><td style="padding-top:12px;color:#6b7280;font-size:14px;">Cancellation Status</td><td align="right" style="padding-top:12px;color:#111827;font-weight:bold;">{{ $cancellationStatus }}</td></tr>
            <tr><td style="padding-top:12px;color:#6b7280;font-size:14px;">Refund Status</td><td align="right" style="padding-top:12px;color:#111827;font-weight:bold;">{{ $refundStatus }}</td></tr>
            @if(!empty($refundAmount))
                <tr><td style="padding-top:12px;color:#6b7280;font-size:14px;">Refund Amount</td><td align="right" style="padding-top:12px;color:#111827;font-weight:bold;">{{ $refundCurrency }} {{ $refundAmount }}</td></tr>
            @endif
        </table>
    </x-slot:details>

    <p>If a refund is applicable, it will be processed according to the payment timeline shown in your account or the provider policy.</p>
</x-email-layout>
