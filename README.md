# Do They Represent Me? — Victoria

A neutral tool for the 2026 Victorian state election. Answer a set of policy questions and see how Victorian parties and your local MPs actually voted in the Parliament of Victoria.

It is also a small "TheyVoteForYou for Victoria": a Laravel pipeline that ingests Legislative Assembly and Legislative Council divisions from Hansard, then scores members and parties against curated policies.

> The original federal version (built on the TheyVoteForYou API) is preserved at the `v1-federal` tag.

## Stack

- Laravel 13 / PHP 8.5, hosted on Laravel Cloud (Serverless Postgres, KV Store, Bucket, managed queues, scheduler)
- Public site: Blade + Alpine (CSP build) + Tailwind 4
- Curation: Filament 5 at `/admin` (allowlisted, MFA required)
- Tests: Pest 5 · Static analysis: Larastan · Style: Pint

## Local development

Requires PHP 8.5, Composer, Node 24 + pnpm, and Postgres (Herd provides all of these).

```bash
composer install
pnpm install
cp .env.example .env && php artisan key:generate
createdb do_they_represent_me && createdb do_they_represent_me_testing
php artisan migrate
composer run dev
```

Set `ADMIN_EMAILS` in `.env` to allow a curator into `/admin`, then create the account with `php artisan make:filament-user`.

## Checks

```bash
php artisan test --compact
pnpm run test:js
vendor/bin/pint --test
vendor/bin/phpstan analyse
composer audit && pnpm audit --prod
```

## Launch plan

M0 to M4 are live, with 22 published questions in a soft launch. M5 and M6 (candidates, final QA and the public launch by 17 November) start on 2 November; the checklist, dates and decisions are in [docs/launch-plan.md](docs/launch-plan.md).

## Data & licensing

Voting records are sourced from the Parliament of Victoria's Votes and Proceedings (Assembly) and Minutes of the Proceedings (Council). Every division links back to its source document.
