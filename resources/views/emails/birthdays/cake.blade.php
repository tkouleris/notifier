{{-- Warm and elegant: cream paper, gold rules, serif type and a cake with candles. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $wish }}</title>
</head>
<body style="margin:0; padding:0; background:#f5efe4;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5efe4; font-family:Georgia, 'Times New Roman', serif;">
    <tr>
        <td align="center" style="padding:40px 16px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:540px; background:#fffdf8; border:1px solid #c9a227;">
                <tr>
                    <td style="padding:8px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e6d29a;">
                            <tr>
                                <td style="padding:44px 36px; text-align:center;">
                                    <div style="font-size:64px; line-height:1.1; margin:0 0 18px;">🎂</div>
                                    <p style="margin:0 0 10px; font-size:12px; letter-spacing:4px; text-transform:uppercase; color:#a16207;">
                                        {{ $age ? 'Celebrating '.$age.' birthday' : 'A day to celebrate' }}
                                    </p>
                                    <h1 style="margin:0; font-size:32px; line-height:1.3; font-weight:normal; color:#3f2a14;">{{ $wish }}</h1>
                                    <table role="presentation" align="center" cellpadding="0" cellspacing="0" style="margin:24px auto;">
                                        <tr>
                                            <td style="width:60px; border-top:1px solid #c9a227;"></td>
                                            <td style="padding:0 10px; font-size:12px; line-height:1; color:#c9a227;">&#10022;</td>
                                            <td style="width:60px; border-top:1px solid #c9a227;"></td>
                                        </tr>
                                    </table>
                                    <p style="margin:0 0 24px; font-size:17px; line-height:1.7; color:#57412a;">{{ $intro }}</p>
                                    @if ($note)
                                        <p style="margin:0 0 28px; font-size:17px; line-height:1.7; font-style:italic; color:#6b4f2e;">&ldquo;{!! nl2br(e($note)) !!}&rdquo;</p>
                                    @endif
                                    <p style="margin:0; font-size:18px; font-style:italic; color:#a16207;">{{ $salutation }}</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
