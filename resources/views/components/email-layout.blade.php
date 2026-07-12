@props([
    'title' => config('app.name'),
    'heading' => config('app.name'),
    'subtitle' => null,
    'footerName' => null,
])
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;padding:40px;">
                    <tr>
                        <td align="center">
                            <h1 style="margin:0;color:#111827;font-size:28px;">{{ $heading }}</h1>
                            @if(!empty($subtitle))
                                <p style="color:#6b7280;margin-top:8px;">{{ $subtitle }}</p>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td style="padding-top:24px;color:#374151;font-size:16px;line-height:26px;">
                            {{ $slot }}
                        </td>
                    </tr>

                    @isset($details)
                        <tr>
                            <td style="padding:24px 0;">
                                {{ $details }}
                            </td>
                        </tr>
                    @endisset

                    @isset($actions)
                        <tr>
                            <td align="center" style="padding:32px 0;">
                                {{ $actions }}
                            </td>
                        </tr>
                    @endisset

                    <tr>
                        <td style="padding-top:32px;color:#9ca3af;font-size:13px;">
                            <p style="margin:0;">© {{ date('Y') }} {{ $footerName ?? config('app.name') }}. All rights reserved.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
