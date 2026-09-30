<x-layouts.public title="Privacy" description="What Do They Represent Me? does and doesn't collect.">
    <div class="mx-auto flex max-w-2xl flex-col gap-8 px-4 py-10">
        <header class="flex flex-col gap-3">
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">Privacy</h1>
            <p class="text-lg text-zinc-600 dark:text-zinc-400">Your answers stay in your browser. We don't track you.</p>
        </header>

        <x-prose>
            <h2>Your answers and your district</h2>
            <ul>
                <li>Your answers, and the district you choose, are kept only in your browser: in its local storage, so you can pick up where you left off, and in the part of a results link after the <code>#</code>. Browsers never send that part of a link to a website, so your answers never reach us.</li>
                <li>Your results are calculated in your browser, from a file of voting records that is the same for everyone.</li>
                <li>The suburb search downloads the whole list of suburbs once and searches it in your browser. What you type is never sent to us.</li>
                <li>If you share a results link, anyone who opens it can see the answers in it.</li>
                <li>To remove your saved answers, use "Clear my answers and start again" in the quiz, or clear this site's data in your browser.</li>
            </ul>

            <h2>No cookies, analytics or tracking</h2>
            <ul>
                <li>The public pages set no cookies.</li>
                <li>There are no analytics, advertising or social media scripts, and nothing is loaded from other companies' servers.</li>
                <li>We don't use your data to profile you, and we have no data to sell or share.</li>
            </ul>

            <h2>Server logs</h2>
            <p>
                Like every website, the servers that host this site (Laravel Cloud) keep short-term technical logs of requests, which include your IP address, the page requested and your browser type. These are used only to keep the site running and secure. They never contain your answers.
            </p>

            <h2>Administrators</h2>
            <p>The admin area, used only by the people who maintain the site, uses a cookie to keep them signed in. Visitors never see it.</p>

            <h2>Contact</h2>
            <p>
                @if (config('site.contact_email'))
                    Questions about privacy: <a href="mailto:{{ config('site.contact_email') }}">{{ config('site.contact_email') }}</a>.
                @else
                    See the <a href="{{ route('about') }}">about page</a> for how to get in touch.
                @endif
            </p>
        </x-prose>
    </div>
</x-layouts.public>
