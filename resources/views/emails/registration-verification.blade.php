<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Verify your email - {{ $siteName }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f7f6;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f4f7f6;padding:24px 12px;">
<tr><td align="center">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:620px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 2px 12px rgba(15,23,42,.08);">
    <tr><td style="height:6px;background:{{ $primaryColor }};font-size:0;line-height:0;">&nbsp;</td></tr>
    <tr><td align="center" style="padding:34px 38px 16px;">
        <div style="font-size:28px;font-weight:700;line-height:1.25;color:#111827;">{{ $siteName }}</div>
        <div style="width:54px;height:4px;border-radius:999px;background:{{ $primaryColor }};margin:14px auto 0;"></div>
    </td></tr>
    <tr><td style="padding:12px 40px 38px;">
        <div style="font-size:22px;font-weight:700;line-height:1.4;margin-bottom:16px;">Hi {{ $recipientName !== '' ? $recipientName : 'there' }},</div>
        <div style="font-size:16px;line-height:1.7;color:#4b5563;margin-bottom:22px;">
            Welcome to {{ $siteName }}. Please verify your email address to finish securing your account.
        </div>
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="margin:0 auto 28px;">
            <tr><td bgcolor="{{ $primaryColor }}" style="border-radius:8px;background:{{ $primaryColor }};">
                <a href="{{ $verificationUrl }}" style="display:inline-block;padding:14px 28px;color:#ffffff;text-decoration:none;font-size:16px;font-weight:700;border-radius:8px;background:{{ $primaryColor }};">Verify My Email</a>
            </td></tr>
        </table>
        <div style="font-size:14px;line-height:1.65;color:#6b7280;border-top:1px solid #e5e7eb;padding-top:20px;">
            If you did not create this account, you can safely ignore this email.
        </div>
        <div style="font-size:14px;line-height:1.65;color:#6b7280;margin-top:18px;">Regards,<br><strong style="color:#1f2937;">{{ $siteName }}</strong></div>
    </td></tr>
</table>
<div style="max-width:620px;text-align:center;font-size:12px;line-height:1.6;color:#9ca3af;padding:16px 20px 0;">Account verification from {{ $siteName }}.</div>
</td></tr>
</table>
</body>
</html>
