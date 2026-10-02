@php
    $sections = [
        'short-version' => 'The short version',
        'answers' => 'Your quiz answers',
        'counting' => 'Counting visits',
        'cookies' => 'Cookies',
        'contact' => 'If you contact us',
        'hosting' => 'Hosting and logs',
        'requests' => 'Questions and requests',
    ];
    $shortVersion = [
        ['title' => 'Anonymous counts only', 'text' => 'We count page views and how far people get, never your answers.'],
        ['title' => 'No tracking cookies', 'text' => 'The only cookies protect the contact form and the site.'],
        ['title' => 'Answers stay with you', 'text' => 'They never leave your device.'],
    ];
@endphp

<x-layouts.public page-type="info" title="Privacy" description="What Do They Represent Me? does and doesn't collect.">
    <div class="mx-auto flex max-w-page flex-col px-5 pb-12 pt-8 lg:flex-row lg:items-start lg:gap-24 lg:pb-24 lg:pt-18">
        <div class="flex min-w-0 flex-col gap-6 lg:w-[704px] lg:shrink-0 lg:gap-12">
            <x-page-header eyebrow="Privacy" title="Your answers are yours">
                We built this so you can use it without telling us anything. Here's exactly what happens to your information. Last updated 2 October 2026.
            </x-page-header>

            <x-on-this-page :sections="$sections" disclosure />

            <x-info-section id="short-version" number="01" title="The short version" first>
                <ul class="flex flex-col gap-2 lg:flex-row lg:gap-3">
                    @foreach ($shortVersion as $point)
                        <li class="flex flex-1 items-center gap-3 rounded-md bg-surface px-4 py-3.5 text-small leading-[18px] lg:flex-col lg:items-start lg:gap-1.5 lg:p-4 lg:leading-5">
                            <svg width="16" height="16" viewBox="0 0 14 14" aria-hidden="true" class="shrink-0 lg:hidden"><path d="M2.5 7.5L5.5 10.5L11.5 3.5" fill="none" stroke="currentColor" stroke-width="1.75" /></svg>
                            <p>
                                <span class="lg:block lg:pb-1.5 lg:text-[15px] lg:font-semibold lg:leading-[18px]">{{ $point['title'] }}<span class="lg:hidden">.</span></span>
                                <span class="lg:block lg:text-ink-muted">{{ $point['text'] }}</span>
                            </p>
                        </li>
                    @endforeach
                </ul>
            </x-info-section>

            <x-info-section id="answers" number="02" title="Your quiz answers">
                <x-prose>
                    <p>
                        Your answers, and your district if you choose one, are saved in your browser's local storage so you can come back later. Your results are worked out on your device, and the suburb search runs in your browser too, so what you type is never sent to us.
                    </p>
                    <p>
                        If you copy a link to your results, your answers go in the part of the link after the <code>#</code>. Browsers never send that part to a server, so we can't see it even when someone opens the link. Anyone you share it with can see your answers.
                    </p>
                    <p>
                        If you share your results as a picture, it is drawn on your device and never uploaded. If you give a name, it goes in the link the same way, after the <code>#</code>. If someone sends you a link to compare your answers with theirs, their answers come in the link and stay in your browser only while the tab is open.
                    </p>
                    <p class="text-ink-muted">To remove them, choose “Start again” on the quiz, or clear this site's data in your browser.</p>
                </x-prose>
            </x-info-section>

            <x-info-section id="counting" number="03" title="Counting visits">
                <dl class="flex flex-col">
                    <x-fact term="What we count" wide>Which pages are viewed, how far through the quiz people get, whether they reach their results, and which share buttons are used.</x-fact>
                    <x-fact term="What we never count" wide>Your answers, your results, which parties you match, your district, what you type into the suburb search, a name in a link, or the part of any link after the <code>#</code>.</x-fact>
                    <x-fact term="Who does the counting" wide>Your browser sends each count to this site, and this site passes it on to PostHog, our analytics provider, which stores it in the United States. We don't pass on your IP address, and PostHog is set to discard it. Your browser never contacts PostHog itself.</x-fact>
                    <x-fact term="On your device" wide>A random code in your browser's session storage, so one visit's counts join up. It's gone when you close the tab. It isn't a cookie, it isn't shared with any other site, and it can't identify you.</x-fact>
                    <x-fact term="How long" wide>The counts are kept for at most 12 months.</x-fact>
                    <x-fact term="Turn it off" wide>
                        <span x-data="analyticsChoice" class="flex flex-col items-start gap-2.5">
                            <span x-show="signalled" x-cloak>Your browser is asking sites not to track it, so counting is already off here.</span>
                            <span x-show="!signalled" x-cloak class="flex flex-col items-start gap-2.5">
                                <span role="status" x-text="status"></span>
                                <button type="button" x-on:click="toggle" x-text="label" class="flex h-10 items-center rounded-md border border-rule-strong px-4 text-small font-medium text-ink hover:border-ink"></button>
                            </span>
                        </span>
                    </x-fact>
                </dl>
            </x-info-section>

            <x-info-section id="cookies" number="04" title="Cookies">
                <dl class="flex flex-col">
                    <x-fact term="Every page except Contact" wide>
                        The public pages set no cookies. The only exception is Cloudflare, which carries the site's traffic for our host, Laravel Cloud. It may set one security cookie, <code>__cf_bm</code>, to tell visitors from automated bots. It lasts 30 minutes, holds nothing about you or your answers, and isn't used for tracking.
                    </x-fact>
                    <x-fact term="Contact page" wide>
                        Two essential cookies, a session cookie and an <code>XSRF-TOKEN</code> security cookie. They stop forged form submissions and keep any error messages while you fix the form. They expire after 2 hours.
                    </x-fact>
                    <x-fact term="Admin area" wide>
                        A sign-in cookie for the person who maintains the site. Visitors never see it.
                    </x-fact>
                </dl>
            </x-info-section>

            <x-info-section id="contact" number="05" title="If you contact us">
                <dl class="flex flex-col">
                    <x-fact term="What we keep" wide>Your email, your name if you give it, the topic, your message and the page it was about.</x-fact>
                    <x-fact term="Why" wide>Only to reply and to fix mistakes. Never for marketing, never shared or sold.</x-fact>
                    <x-fact term="Who sees it" wide>Only Dan Ferguson, who runs the site. A copy is emailed to him through his own email provider, Namecheap Private Email.</x-fact>
                    <x-fact term="How long" wide>Deleted automatically 12 months after you send it, or sooner if you ask.</x-fact>
                    <x-fact term="Spam check" wide>The form uses Cloudflare Turnstile to tell people from bots. Your browser loads it from Cloudflare, which sees your IP address and some details about your browser, but never your message. Cloudflare doesn't use it for advertising.</x-fact>
                </dl>
            </x-info-section>

            <x-info-section id="hosting" number="06" title="Hosting and logs">
                <x-prose>
                    <p>
                        The site is hosted on Laravel Cloud in Sydney. Like any web server, it keeps short-lived technical logs of each request, including your IP address, browser type, country and the page requested, so we can keep the site running and secure. Laravel Cloud deletes these logs after 1 day. We never use them to identify visitors, and they never contain your answers.
                    </p>
                    <p class="text-ink-muted">There are no advertising or social media scripts, and nothing is loaded from other companies' servers. Your browser only ever talks to this site.</p>
                </x-prose>
            </x-info-section>

            <x-info-section id="requests" number="07" title="Questions and requests">
                <x-prose>
                    <p>
                        Ask to see or delete anything you've sent us, or ask a question about this policy, through the <a href="{{ route('contact') }}">contact page</a>. We'll do our best to respond within 30 days, and handle each request case by case.
                    </p>
                </x-prose>
                <a href="{{ route('contact') }}" class="flex h-12 items-center justify-center rounded-md border border-rule-strong text-[15px] font-medium hover:border-ink lg:hidden">Contact us</a>
                <a href="{{ route('contact') }}" class="link hidden self-start text-[15px] leading-[18px] lg:inline">Contact us →</a>
            </x-info-section>
        </div>

        <aside class="hidden flex-col gap-8 lg:sticky lg:top-8 lg:flex lg:w-80 lg:shrink-0">
            <x-on-this-page :sections="$sections" />
        </aside>
    </div>
</x-layouts.public>
