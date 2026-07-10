<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Booking Rescheduled</title>
</head>

<body style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;padding:40px;">
                    <tr>
                        <td align="center">
                            <h1 style="margin:0;color:#111827;font-size:28px;">Booking Rescheduled</h1>
                            <p style="color:#6b7280;margin-top:8px;">Your itinerary has been updated successfully.</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding-top:24px;color:#374151;font-size:16px;line-height:26px;">
                            <p>Hello {{ $name ?? 'Customer' }},</p>

                            <p>
                                Your booking with <strong>{{ $tenant }}</strong> has been rescheduled.
                                The existing booking reference is still valid and now points to the updated itinerary.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:24px 0;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border-radius:10px;padding:20px;">
                                <tr>
                                    <td style="color:#6b7280;font-size:14px;">Booking Reference</td>
                                    <td align="right" style="color:#111827;font-size:18px;font-weight:bold;">
                                        {{ $order->booking_reference ?? 'N/A' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-top:12px;color:#6b7280;font-size:14px;">Order Status</td>
                                    <td align="right" style="padding-top:12px;color:#111827;font-weight:bold;">
                                        {{ $order->status ?? 'Rescheduled' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-top:12px;color:#6b7280;font-size:14px;">Change Reference</td>
                                    <td align="right" style="padding-top:12px;color:#111827;font-weight:bold;">
                                        {{ data_get($change, 'order_change_id', 'N/A') }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding-bottom:24px;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="background:#eff6ff;border-radius:10px;padding:20px;">
                                <tr>
                                    <td style="color:#1e3a8a;font-size:14px;font-weight:bold;">Latest itinerary snapshot</td>
                                </tr>
                                <tr>
                                    <td style="padding-top:10px;color:#1f2937;font-size:14px;line-height:22px;">
                                        {{ data_get($change, 'latest_order_snapshot.data.id', data_get($change, 'latest_order_snapshot.id', 'Updated itinerary attached to your booking record.')) }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="color:#6b7280;font-size:14px;line-height:24px;">
                            <p>
                                If there was any payment difference, it is recorded in your booking details.
                            </p>

                            <p>
                                Please keep this email for your records. You can review the updated itinerary from your booking dashboard.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding-top:32px;color:#9ca3af;font-size:13px;">
                            <p style="margin:0;">© {{ date('Y') }} {{ $tenant }}. All rights reserved.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
