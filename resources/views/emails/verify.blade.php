<!DOCTYPE html>

<html>

<head>
    <meta charset="UTF-8">
    <title>Verify Email</title>
</head>

<body style="margin:0;padding:0;background:#f5f7fb;font-family:Arial,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 0;">
        <tr>
            <td align="center">

                <table width="600" cellpadding="0" cellspacing="0"
                    style="background:#ffffff;border-radius:12px;padding:40px;">

                    <tr>
                        <td align="center">
                            <h1 style="margin:0;color:#111827;">
                                Verify Your Email
                            </h1>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding-top:24px;color:#374151;font-size:16px;line-height:26px;">

                            <p>Hello {{ $name }},</p>

                            <p>
                                Thank you for registering with
                                <strong>{{ $tenant }}</strong>.
                            </p>

                            <p>
                                Please verify your email address by clicking the button below.
                                This verification link is valid for a limited time and can only
                                be used once.
                            </p>

                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:32px 0;">

                            <a href="{{ $verificationUrl }}"
                                style="
                                background:#2563eb;
                                color:#ffffff;
                                text-decoration:none;
                                padding:14px 28px;
                                border-radius:8px;
                                display:inline-block;
                                font-weight:bold;">
                                Verify Email Address
                            </a>

                        </td>
                    </tr>

                    <tr>
                        <td style="color:#6b7280;font-size:14px;line-height:24px;">

                            <p>
                                If the button above does not work, copy and paste the following
                                link into your browser:
                            </p>

                            <p style="word-break:break-all;">
                                {{ $verificationUrl }}
                            </p>

                            <p>
                                If you did not create this account, you can safely ignore this email.
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
</body>

</html>