@props(['text'])

{{-- Text from the workbook or the Parliament's records, split into paragraphs at line breaks. Always escaped. --}}
@foreach (preg_split('/\R+/', trim((string) $text)) ?: [] as $paragraph)
    @if (trim($paragraph) !== '')
        <p {{ $attributes }}>{{ trim($paragraph) }}</p>
    @endif
@endforeach
