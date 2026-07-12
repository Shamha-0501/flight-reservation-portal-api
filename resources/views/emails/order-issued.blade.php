<x-email-layout
    title="Booking Confirmed"
    heading="Booking Confirmed"
    subtitle="Your flight order has been successfully issued."
    :footer-name="$tenant"
>
    <p>Hello {{ $name ?? 'Customer' }},</p>
    <p>Thank you for booking with <strong>{{ $tenant }}</strong>. Your booking reference PDF is attached to this email.</p>

    <x-slot:details>
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border-radius:10px;padding:20px;">
            <tr>
                <td style="color:#6b7280;font-size:14px;">Booking Reference</td>
                <td align="right" style="color:#111827;font-size:18px;font-weight:bold;">{{ $order->booking_reference ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="padding-top:12px;color:#6b7280;font-size:14px;">Order Status</td>
                <td align="right" style="padding-top:12px;color:#111827;font-weight:bold;">{{ ucfirst($order->status ?? 'created') }}</td>
            </tr>
            <tr>
                <td style="padding-top:12px;color:#6b7280;font-size:14px;">Total Amount</td>
                <td align="right" style="padding-top:12px;color:#111827;font-weight:bold;">{{ $order->total_currency }} {{ $order->total_amount }}</td>
            </tr>
        </table>
    </x-slot:details>

    <p>Please keep this email and attached PDF for your records.</p>
    <p>If you need to view, modify, or cancel your booking, use your booking reference.</p>
</x-email-layout>
