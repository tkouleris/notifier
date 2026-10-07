@props(['url'])
{{-- Every Notifier email opens with the logo; its colours come from the mail theme. --}}
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ asset('images/logo-mark.png') }}" class="logo" width="64" height="64" alt="{{ config('app.name') }} logo">
<span class="brand-name">{{ $slot }}</span>
</a>
</td>
</tr>
