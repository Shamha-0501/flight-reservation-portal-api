<x-email-layout
    title="Refund Confirmed"
    heading="Refund Confirmed"
    subtitle="Your refund has been marked as complete."
    :footer-name="$tenantName"
>
    <p>Hello {{ $name ?? 'Customer' }},</p>
    <p>Your refund for booking <strong>{{ $bookingReference }}</strong> has been confirmed for <strong>{{ $tenantName }}</strong>.</p>

    <x-slot:details>
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border-radius:10px;padding:20px;">
            <tr><td style="color:#6b7280;font-size:14px;">Refund Status</td><td align="right" style="color:#111827;font-weight:bold;">{{ $refundStatus }}</td></tr>
            @if(!empty($reference))
                <tr><td style="padding-top:12px;color:#6b7280;font-size:14px;">Reference</td><td align="right" style="padding-top:12px;color:#111827;font-weight:bold;">{{ $reference }}</td></tr>
            @endif
            @if(!empty($notes))
                <tr><td style="padding-top:12px;color:#6b7280;font-size:14px;">Notes</td><td align="right" style="padding-top:12px;color:#111827;font-weight:bold;">{{ $notes }}</td></tr>
            @endif
        </table>
    </x-slot:details>

    <p>Please keep this email for your records. If the refund takes time to appear in your account, the timing can depend on your payment provider.</p>
</x-email-layout>
