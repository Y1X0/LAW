<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
</head>
<body style="margin:0; padding:0; background:#f1f5f9; font-family:'Segoe UI',Tahoma,Arial,sans-serif; color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden;">
                    <tr>
                        <td style="background:#0f2f4c; padding:20px 28px; text-align:right;">
                            <span style="color:#ffffff; font-size:16px; font-weight:600;">{{ config('mail.from.name', config('app.name')) }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px; text-align:right;">
                            <h1 style="margin:0 0 12px; font-size:18px; font-weight:700; color:#0f172a;">{{ $title }}</h1>
                            @if (filled($body))
                                <p style="margin:0; font-size:15px; line-height:1.7; color:#334155;">{{ $body }}</p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 28px; border-top:1px solid #e2e8f0; text-align:right;">
                            <p style="margin:0; font-size:12px; color:#94a3b8; line-height:1.6;">
                                هذه رسالة آلية من نظام إدارة المكتب. تجد تفاصيل الإشعار داخل النظام.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
