{{-- A bright party card: deep purple night, confetti strips and a hot-pink banner. --}}
@php
    $confetti = ['#f43f5e', '#facc15', '#22d3ee', '#a3e635', '#fb923c', '#e879f9', '#60a5fa', '#f43f5e', '#facc15', '#22d3ee', '#a3e635', '#fb923c'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $wish }}</title>
</head>
<body style="margin:0; padding:0; background:#2e1065;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#2e1065; font-family:Verdana, Geneva, Arial, sans-serif;">
    <tr>
        <td align="center" style="padding:32px 16px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background:#ffffff; border-radius:12px; overflow:hidden;">
                <tr>
                    <td style="padding:16px 20px 0; text-align:center; line-height:0;">
                        @foreach ($confetti as $i => $color)
                            <span style="display:inline-block; width:{{ $i % 2 ? 8 : 12 }}px; height:{{ $i % 3 ? 8 : 16 }}px; margin:0 6px {{ $i % 2 ? 10 : 0 }}px; background:{{ $color }}; border-radius:{{ $i % 3 ? '50%' : '2px' }};"></span>
                        @endforeach
                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding:20px 24px 16px; font-size:52px; line-height:1;">🎉</td>
                </tr>
                <tr>
                    <td style="background:#db2777; background-image:linear-gradient(90deg, #db2777, #7c3aed); padding:28px 24px; text-align:center;">
                        @if ($age)
                            <p style="margin:0 0 12px; font-size:64px; line-height:1; font-weight:bold; color:#facc15;">{{ $age }}</p>
                        @endif
                        <h1 style="margin:0; font-size:26px; line-height:1.3; color:#ffffff; text-transform:uppercase; letter-spacing:1px;">{{ $wish }}</h1>
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px 32px 8px; text-align:center;">
                        <p style="margin:0 0 24px; font-size:16px; line-height:1.7; color:#3b0764;">{{ $intro }}</p>
                        @if ($note)
                            <div style="border:2px dashed #e879f9; border-radius:10px; padding:20px 22px; margin:0 0 24px; font-size:16px; line-height:1.6; color:#4c1d95; text-align:left;">{!! nl2br(e($note)) !!}</div>
                        @endif
                        <p style="margin:0 0 8px; font-size:16px; font-weight:bold; color:#db2777;">{{ $salutation }} 🥳</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:12px 20px 20px; text-align:center; line-height:0;">
                        @foreach (array_reverse($confetti) as $i => $color)
                            <span style="display:inline-block; width:{{ $i % 2 ? 12 : 8 }}px; height:{{ $i % 3 ? 8 : 16 }}px; margin:{{ $i % 2 ? 10 : 0 }}px 6px 0; background:{{ $color }}; border-radius:{{ $i % 3 ? '50%' : '2px' }};"></span>
                        @endforeach
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
