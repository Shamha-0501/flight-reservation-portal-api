<x-email-layout
    title="Reschedule Request Received"
    heading="Reschedule Request Received"
    subtitle="We are processing your change request."
    :footer-name="$tenantName"
>
    <p>Hello {{ $name ?? 'Customer' }},</p>
    <p>We have received your reschedule request for booking <strong>{{ $bookingReference }}</strong> with <strong>{{ $tenantName }}</strong>.</p>
    <p>Your request is now recorded and the updated itinerary will be shared once it is ready.</p>

    <x-slot:details>
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border-radius:10px;padding:20px;">
            <tr><td style="color:#6b7280;font-size:14px;">Request ID</td><td align="right" style="color:#111827;font-weight:bold;">{{ $requestId ?? 'N/A' }}</td></tr>
            <tr><td style="padding-top:12px;color:#6b7280;font-size:14px;">Booking Reference</td><td align="right" style="padding-top:12px;color:#111827;font-weight:bold;">{{ $bookingReference }}</td></tr>
        </table>
    </x-slot:details>
</x-email-layout>
