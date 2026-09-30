{{-- Every party with members in this Parliament, in equal widths and alphabetical order, so no party is given more weight. Decorative only. --}}
<div aria-hidden="true" {{ $attributes->class('flex h-1 shrink-0') }}>
    @foreach (['ajp', 'dlp', 'ffv', 'grn', 'alp', 'lcv', 'lib', 'lbt', 'nat', 'onp', 'sff'] as $code)
        <div class="h-1 flex-1 bg-party-{{ $code }}"></div>
    @endforeach
</div>
