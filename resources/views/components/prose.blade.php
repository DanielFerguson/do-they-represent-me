{{-- Long-form text: methodology, privacy and about pages. --}}
<div {{ $attributes->class([
    'flex flex-col gap-4 leading-relaxed text-zinc-700 dark:text-zinc-300',
    '[&_h2]:mt-6 [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:tracking-tight [&_h2]:text-zinc-900 dark:[&_h2]:text-zinc-100',
    '[&_h3]:mt-2 [&_h3]:font-semibold [&_h3]:text-zinc-900 dark:[&_h3]:text-zinc-100',
    '[&_a]:underline [&_a]:underline-offset-4',
    '[&_ul]:flex [&_ul]:list-disc [&_ul]:flex-col [&_ul]:gap-1 [&_ul]:pl-5',
    '[&_ol]:flex [&_ol]:list-decimal [&_ol]:flex-col [&_ol]:gap-1 [&_ol]:pl-5',
    '[&_code]:text-sm',
]) }}>
    {{ $slot }}
</div>
