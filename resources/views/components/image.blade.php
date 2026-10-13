@if ($inline)
<div {{ $attributes->merge(['role' => 'img', 'aria-label' => $alt]) }}>{!! $svg() !!}</div>
@else
<img src="{{ $src() }}" width="{{ $pixels }}" height="{{ $pixels }}" alt="{{ $alt }}" {{ $attributes }}>
@endif
