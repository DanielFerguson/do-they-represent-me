@props(['code' => null])

@php
    $known = ['ajp', 'alp', 'dlp', 'ffv', 'grn', 'ind', 'lbt', 'lcv', 'lib', 'nat', 'onp', 'sff'];
    $code = strtolower((string) $code);
@endphp

{{-- A small party colour mark beside a party's name. The name is always shown in text, so the colour is never the only cue. --}}
<span aria-hidden="true" {{ $attributes->class(['inline-block size-2.5 shrink-0 rounded-full', in_array($code, $known, true) ? 'bg-party-'.$code : 'bg-party']) }}></span>
