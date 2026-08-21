<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $siteName }} Verification Code</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f6f8;padding:28px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5e7eb;">
                <tr>
                    <td style="height:7px;background:{{ $primaryColor }};font-size:0;line-height:0;">&nbsp;</td>
                </tr>
                <tr>
                    <td align="center" style="padding:28px 32px 16px;">
                        @if(!empty($logoUrl))
                            <img src="{{ $logoUrl }}" alt="{{ $siteName }}" style="max-height:58px;max-width:200px;margin-bottom:14px;">
                        @endif
                        <div style="font-size:26px;font-weight:700;color:#111827;">{{ $siteName }}</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 38px 34px;">
                        <p style="margin:0 0 18px;font-size:18px;font-weight:600;">Hi {{ $recipientName !== '' ? $recipientName : 'there' }},</p>
                        <p style="margin:0 0 18px;font-size:16px;line-height:1.65;color:#4b5563;">Use the verification code below to securely sign in to your {{ $siteName }} account.</p>

                        <div style="margin:26px 0;text-align:center;">
                            <div style="display:inline-block;min-width:220px;padding:18px 28px;border-radius:12px;background:{{ $primaryColor }};color:#ffffff;font-size:32px;font-weight:800;letter-spacing:8px;">{{ $code }}</div>
                        </div>

                        <p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#4b5563;">This code expires in <strong>10 minutes</strong>.</p>
                        <p style="margin:0;font-size:14px;line-height:1.6;color:#6b7280;">If you did not request this code, you can safely ignore this email. Never share your verification code with anyone.</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 32px;background:#f9fafb;border-top:1px solid #e5e7eb;text-align:center;font-size:13px;color:#6b7280;">
                        {{ $siteName }} &mdash; your personal planning and productivity companion.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
