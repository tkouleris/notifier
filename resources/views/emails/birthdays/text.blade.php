{{-- Plain-text part shared by every birthday layout, so nothing is HTML-escaped. --}}
{!! $greeting !!}

{!! $intro !!}
@if ($note)

{!! $note !!}
@endif

{!! $salutation !!}
