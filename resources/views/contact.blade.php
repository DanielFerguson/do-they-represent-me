@php
    $fieldClass = 'w-full rounded-md border border-rule-strong bg-ground px-3.5 text-base text-ink placeholder:text-ink-muted focus-visible:border-ink aria-invalid:border-2 aria-invalid:border-ink';
    $errorIcon = '<svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true" class="shrink-0"><circle cx="7" cy="7" r="6" fill="none" stroke="currentColor" stroke-width="1.5" /><path d="M7 4v3.5M7 9.5v.5" fill="none" stroke="currentColor" stroke-width="1.5" /></svg>';
@endphp

<x-layouts.public title="Contact" description="Report a mistake, ask a question or get in touch for media.">
    <div class="mx-auto flex max-w-page flex-col gap-24 px-5 pb-12 pt-8 lg:flex-row lg:items-start lg:pb-24 lg:pt-18">
        <div class="flex min-w-0 flex-col gap-8 lg:w-[704px] lg:shrink-0 lg:gap-12">
            @if (session('sent'))
                <div class="flex flex-col gap-5 pt-6">
                    <div class="flex size-12 items-center justify-center rounded-full bg-ink text-ground" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 14 14"><path d="M2.5 7.5L5.5 10.5L11.5 3.5" fill="none" stroke="currentColor" stroke-width="1.75" /></svg>
                    </div>
                    <h1 class="text-h1-mobile font-semibold tracking-display lg:text-h1">Thanks, we've got your message</h1>
                    <p class="text-body leading-[25px] text-ink-muted lg:leading-7">
                        We'll check it against the official record and reply to {{ session('sent') }}. Corrections usually take a few days.
                    </p>
                    <div class="flex flex-col gap-2.5 pt-2 sm:flex-row sm:items-center sm:gap-5">
                        @if (session('back'))
                            <a href="{{ session('back') }}" class="flex h-12 items-center justify-center rounded-md border border-rule-strong px-7 text-[15px] font-medium hover:border-ink">Back to where you were</a>
                        @endif
                        <a href="{{ route('home') }}" class="link text-center text-small">Take the quiz</a>
                    </div>
                </div>
            @else
                <header class="flex flex-col gap-2.5 lg:gap-4">
                    <p class="eyebrow">Contact</p>
                    <h1 class="text-h1-mobile font-semibold tracking-display lg:text-h1">Get in touch</h1>
                    <p class="text-ink-muted lg:text-body">Report a mistake, ask a question or get in touch for media. A person reads every message.</p>
                </header>

                <form method="POST" action="{{ route('contact.store') }}" novalidate class="flex flex-col gap-6 border-t border-ink pt-6">
                    @csrf

                    @if ($errors->any())
                        <div role="alert" tabindex="-1" autofocus class="flex flex-col gap-2 rounded-md border-2 border-ink p-4">
                            <p class="flex items-center gap-2 text-[15px] font-semibold">
                                {!! $errorIcon !!}
                                {{ trans_choice('{1} 1 thing to fix before sending|[2,*] :count things to fix before sending', count($errors->keys())) }}
                            </p>
                            <ul class="flex flex-col gap-2 pl-6 text-small">
                                @foreach ($errors->keys() as $field)
                                    <li><a href="#{{ $field }}" class="underline underline-offset-[3px]">{{ $errors->first($field) }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <fieldset class="flex flex-col gap-2" @error('topic') aria-describedby="topic-error" @enderror>
                        <legend class="mb-2 text-small font-medium">What's it about?</legend>
                        @error('topic')
                            <p id="topic-error" class="flex items-center gap-1.5 text-small font-medium">{!! $errorIcon !!} {{ $message }}</p>
                        @enderror
                        <div class="flex flex-col gap-2 sm:flex-row">
                            @foreach ($topics as $topic)
                                <label class="flex h-12 cursor-pointer items-center gap-2.5 rounded-md border border-rule-strong px-3.5 text-[15px] has-checked:border-2 has-checked:border-ink has-checked:px-[13px] has-checked:font-medium sm:h-13 sm:flex-1 sm:px-4 sm:has-checked:px-[15px]">
                                    <input type="radio" name="topic" value="{{ $topic->value }}" @checked(old('topic', $selectedTopic->value) === $topic->value) @if ($loop->first) id="topic" @endif class="size-4 shrink-0 appearance-none rounded-full border-[1.5px] border-rule-strong checked:border-[5px] checked:border-ink">
                                    {{ $topic->label() }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    @if ($subject)
                        <input type="hidden" name="{{ $subject['field'] }}" value="{{ $subject['slug'] }}">
                        <div class="flex items-center justify-between gap-3 rounded-md bg-surface px-4 py-3">
                            <div class="flex min-w-0 flex-col gap-0.5">
                                <p class="eyebrow">{{ $subject['heading'] }}</p>
                                <p class="text-[15px] leading-[18px]">{{ $subject['text'] }}</p>
                            </div>
                            <a href="{{ route('contact', ['topic' => $selectedTopic->value]) }}" class="link shrink-0 text-small text-ink-muted">Remove</a>
                        </div>
                    @endif

                    <div class="flex flex-col gap-6 sm:flex-row sm:gap-4">
                        <div class="flex flex-1 flex-col gap-1.5">
                            <div class="flex justify-between">
                                <label for="name" class="text-small font-medium">Your name</label>
                                <span class="text-[13px] leading-4 text-ink-muted">Optional</span>
                            </div>
                            @error('name')
                                <p id="name-error" class="flex items-center gap-1.5 text-small font-medium">{!! $errorIcon !!} {{ $message }}</p>
                            @enderror
                            <input id="name" name="name" type="text" value="{{ old('name') }}" maxlength="100" autocomplete="name" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror class="{{ $fieldClass }} h-12">
                        </div>
                        <div class="flex flex-1 flex-col gap-1.5">
                            <label for="email" class="text-small font-medium">Email</label>
                            @error('email')
                                <p id="email-error" class="flex items-center gap-1.5 text-small font-medium">{!! $errorIcon !!} {{ $message }}</p>
                            @enderror
                            <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="254" autocomplete="email" required @error('email') aria-invalid="true" aria-describedby="email-error" @enderror class="{{ $fieldClass }} h-12">
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="message" class="text-small font-medium">Message</label>
                        @error('message')
                            <p id="message-error" class="flex items-center gap-1.5 text-small font-medium">{!! $errorIcon !!} {{ $message }}</p>
                        @enderror
                        <textarea id="message" name="message" rows="6" maxlength="5000" required @if ($selectedTopic === \App\Enums\ContactTopic::Correction) placeholder="What looks wrong, and where did you see the correct information? A link to the source helps us check quickly." @endif @error('message') aria-invalid="true" aria-describedby="message-error" @enderror class="{{ $fieldClass }} min-h-30 py-3 leading-[25px] lg:min-h-40">{{ old('message') }}</textarea>
                    </div>

                    {{-- Hidden from people and screen readers; simple bots fill it in, and their messages are discarded. --}}
                    <div class="hidden" aria-hidden="true">
                        <label for="website">Leave this empty</label>
                        <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:gap-5">
                        <button type="submit" class="h-12 rounded-md bg-ink px-7 text-[15px] font-medium text-ground hover:opacity-85">Send message</button>
                        <p class="text-[13px] leading-[19px] text-ink-muted">We only use your email to reply. Messages are deleted after 12 months. <a href="{{ route('privacy') }}" class="link">Privacy</a></p>
                    </div>
                </form>
            @endif
        </div>

        <aside class="flex flex-col gap-8 lg:w-80 lg:shrink-0" aria-label="About corrections">
            <div class="flex flex-col">
                <h2 class="eyebrow border-b border-rule pb-3">How corrections work</h2>
                <ol class="text-small leading-[21px] text-ink-muted">
                    <li class="flex gap-4 border-b border-rule py-3"><span class="w-5 shrink-0 font-semibold text-ink">1</span> We check your report against the Parliament's official record.</li>
                    <li class="flex gap-4 border-b border-rule py-3"><span class="w-5 shrink-0 font-semibold text-ink">2</span> If it's wrong, we fix it and publish a new version of the data.</li>
                    <li class="flex gap-4 border-b border-rule py-3"><span class="w-5 shrink-0 font-semibold text-ink">3</span> We reply to let you know what we found.</li>
                </ol>
            </div>
            <div class="flex flex-col gap-2 rounded-md bg-surface p-5 text-small leading-[21px]">
                <p class="font-semibold">Parties and candidates</p>
                <p class="text-ink-muted">You're welcome to point out errors too. We treat every report the same way, whoever sends it.</p>
            </div>
        </aside>
    </div>
</x-layouts.public>
