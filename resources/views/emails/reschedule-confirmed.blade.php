<x-email-layout
    title="Reschedule Ready"
    heading="Updated Itinerary Ready"
    subtitle="Your reschedule has moved forward successfully."
    :footer-name="$tenantName"
>
    <p>Hello {{ $name ?? 'Customer' }},</p>
    <p>Your booking <strong>{{ $bookingReference }}</strong> with <strong>{{ $tenantName }}</strong> has been updated and the reschedule details are ready.</p>
    <p>You can review the revised itinerary from your account.</p>

    <x-slot:details>
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border-radius:10px;padding:20px;">
            <tr><td style="color:#6b7280;font-size:14px;">Order Change ID</td><td align="right" style="color:#111827;font-weight:bold;">{{ $orderChangeId ?? 'N/A' }}</td></tr>
            <tr><td style="padding-top:12px;color:#6b7280;font-size:14px;">Booking Reference</td><td align="right" style="padding-top:12px;color:#111827;font-weight:bold;">{{ $bookingReference }}</td></tr>
        </table>
    </x-slot:details>
</x-email-layout>
