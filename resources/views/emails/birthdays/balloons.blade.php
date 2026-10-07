{{-- Light and cheerful: pastel sky, floating balloons, a rounded white card. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $wish }}</title>
</head>
<body style="margin:0; padding:0; background:#e0f2fe;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#e0f2fe; font-family:'Trebuchet MS', Helvetica, Arial, sans-serif;">
    <tr>
        <td align="center" style="padding:32px 16px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;">
                <tr>
                    <td align="center" style="font-size:56px; line-height:1; padding-bottom:12px; letter-spacing:8px;">🎈🎈🎈</td>
                </tr>
                <tr>
                    <td style="background:#ffffff; border-radius:24px; padding:40px 32px; text-align:center; box-shadow:0 6px 18px rgba(3,105,161,.12);">
                        <p style="margin:0 0 8px; font-size:13px; letter-spacing:3px; text-transform:uppercase; color:#ec4899; font-weight:bold;">It&rsquo;s your day</p>
                        <h1 style="margin:0 0 20px; font-size:30px; line-height:1.25; color:#0369a1;">{{ $wish }}</h1>
                        @if ($age)
                            <table role="presentation" align="center" cellpadding="0" cellspacing="0" style="margin:0 auto 24px;">
                                <tr>
                                    <td style="background:#fde68a; color:#92400e; border-radius:999px; padding:8px 22px; font-size:16px; font-weight:bold;">{{ $age }} 🎂</td>
                                </tr>
                            </table>
                        @endif
                        <p style="margin:0 0 24px; font-size:17px; line-height:1.6; color:#334155;">{{ $intro }}</p>
                        @if ($note)
                            <div style="background:#fce7f3; border-radius:18px; padding:20px 24px; margin:0 0 24px; font-size:16px; line-height:1.6; color:#831843; text-align:left;">{!! nl2br(e($note)) !!}</div>
                        @endif
                        <p style="margin:0; font-size:16px; color:#0369a1; font-weight:bold;">{{ $salutation }}</p>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="font-size:28px; padding-top:16px; letter-spacing:10px;">🎈 🎁 🎈</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
