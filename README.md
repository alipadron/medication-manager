# medication-manager

A household medication PWA: schedules that survive real life, plus awareness of what you have
and when you will run out.

Built for a two-person household with a shared schedule, because phone alarms fail in practice:
you miss one, and every following alarm has to be re-shifted by hand.

## What it does

- **Two dosing patterns.** `interval` medicines ("every N hours") are re-anchored to the last
  actual dose instead of resetting at midnight; `fixed-times` medicines run on a daily clock-time
  grid.
- **Stock and refill forecasting.** Each package (blister, box) tracks remaining doses, so you
  re-buy before it empties.
- **Every device.** Web Push through a service worker, so the reminder reaches whichever screen
  is in front of you.
- **Agent access.** A small MCP server exposes the app's own domain so assistants can answer
  questions about the schedule and stock.

## Stack

- `app/` — Angular PWA with a service worker and offline support.
- `backend/` — Laravel API with `laravel-notification-channels/webpush` and `laravel/mcp`.
- SQLite/PostgreSQL locally; deployed on Laravel Cloud.

## Run it

```bash
# API
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve

# App
cd app
npm ci
npm start
```

Generate the VAPID keys for Web Push with `php artisan webpush:vapid` and put them in
`backend/.env`.

## Docs

- `SPEC.md` — product spec: problem, users, behaviour.
- `CONTEXT.md` — domain glossary (medication, package, intake, schedule, dosing pattern).
- `AGENTS.md` and `docs/agents/` — how agents work in this repository.

## Privacy

This is a personal project. The repository contains no real medication data, no household
records and no credentials; local `.env` files and databases stay untracked.
