<!DOCTYPE html>

<html>

<head>
    <meta charset="UTF-8">
    <title>Flight Booking Verification</title>
</head>

<body style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,sans-serif;">

```
<table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 0;">
    <tr>
        <td align="center">

            <table width="600" cellpadding="0" cellspacing="0"
                style="background:#ffffff;border-radius:12px;padding:40px;">

                <tr>
                    <td align="center">
                        <h1 style="margin:0;color:#111827;font-size:28px;">
                            Confirm Your Booking
                        </h1>
                    </td>
                </tr>

                <tr>
                    <td style="padding-top:24px;color:#374151;font-size:16px;line-height:26px;">

                        <p>Hello {{ $name }},</p>

                        <p>
                            We received a request to continue your flight booking with
                            <strong>{{ $tenant }}</strong>.
                        </p>

                        <p>
                            Please use the verification code below to confirm your booking request.
                        </p>

                    </td>
                </tr>

                <tr>
                    <td align="center" style="padding:30px 0;">

                        <div style="
                            display:inline-block;
                            background:#2563eb;
                            color:#ffffff;
                            font-size:32px;
                            font-weight:bold;
                            letter-spacing:8px;
                            padding:18px 32px;
                            border-radius:12px;">
                            {{ $code }}
                        </div>

                    </td>
                </tr>

                <tr>
                    <td style="color:#6b7280;font-size:14px;line-height:24px;">

                        <p>
                            This verification code will expire in
                            <strong>10 minutes</strong>.
                        </p>

                        <p>
                            For your security, do not share this code with anyone.
                        </p>

                        <p>
                            If you did not request this booking verification, you can safely ignore this email.
                        </p>

                    </td>
                </tr>

                <tr>
                    <td style="padding-top:32px;color:#9ca3af;font-size:13px;">

                        <p style="margin:0;">
                            © {{ date('Y') }} {{ $tenant }}.
                            All rights reserved.
                        </p>

                    </td>
                </tr>

            </table>

        </td>
    </tr>

</table>
```

</body>

</html>
