# QResto

QR ordering and floor management for a single restaurant, built on a schema that is ready for more than one. Guests scan the code on their table, order and pay from their phone, in English or Arabic. Staff run the floor, the kitchen pass and the till from the same app, each role seeing only its own screens.

The demo restaurant is **Al Bustan**.

## Stack

Laravel 13, Inertia 3 and React, Tailwind 4, Pest and Vitest. Roles and permissions use `spatie/laravel-permission`, sign-in uses Fortify, and payments go through a gateway abstraction with a fake driver and a Stripe driver.

## Requirements

- PHP 8.3 or newer (CI runs 8.4)
- Composer
- Node 22 and npm
- SQLite, the default database (nothing to install with Herd)

## Getting started

```bash
composer setup
```

This installs PHP and npm dependencies, copies `.env.example` to `.env`, generates the app key, runs the migrations and builds the frontend.

To load the Al Bustan demo (menu, tables, staff, orders and a few weeks of history), set this in `.env` and seed:

```bash
SEED_DEMO_DATA=true
```

```bash
php artisan migrate:fresh --seed
```

Then start the app and Vite together:

```bash
composer dev
```

Seeding twice is safe: row counts and table QR codes stay the same (#44).

## Demo accounts

Every account's password is `password`. Sign in at `/login`.

| Role        | Name         | Email                       |
| ----------- | ------------ | --------------------------- |
| Super admin | Yusuf Aziz   | `yusuf.aziz@al-bustan.test` |
| Manager     | Sara Malik   | `sara.malik@al-bustan.test` |
| Waiter      | Nadia Rahman | `nadia@albustan.co`         |
| Waiter      | Omar Faruk   | `omar.faruk@al-bustan.test` |
| Waiter      | Lin Wu       | `lin.wu@al-bustan.test`     |
| Kitchen     | Kitchen Pass | `kitchen@al-bustan.test`    |
| Runner      | Sami Haddad  | `runner@al-bustan.test`     |
| Cashier     | Layla Nasser | `cashier@al-bustan.test`    |

Waiters can also sign in by scanning a shift QR that a manager issues at `/manager/shift-qr`.

## Trying the guest flow

- `/` is the restaurant's public landing page.
- Each table has a QR code pointing at `/t/{qr_token}`. Print them all from `/app/settings/tables/qr-sheet` (admin), or copy a token from the `tables` table.
- The language switch is in the guest header and in staff profile settings. Arabic flips the whole layout to right-to-left.

To move the demo's orders and times up to today without wiping anything:

```bash
php artisan qresto:demo-refresh
```

## Payments

`QRESTO_PAYMENT_DRIVER=fake` (the default) makes no network calls. Any card number is approved, except `4000 0000 0000 0002`, which is declined, and `4000 0025 0000 3155`, which asks for 3-D Secure. The whole test suite runs on this driver. To try real Stripe test mode, set the driver to `stripe` and fill in the `STRIPE_*` keys. Setup, test cards and local webhooks are in [`docs/payments-stripe.md`](docs/payments-stripe.md).

## Scheduled and maintenance commands

The scheduler runs these; locally, `php artisan schedule:work` runs them.

| Command                            | Runs            | What it does                                                           |
| ---------------------------------- | --------------- | ---------------------------------------------------------------------- |
| `qresto:close-idle-table-sessions` | every 5 minutes | Closes guest table sessions idle past the configured limit             |
| `qresto:probe-gateway`             | every 5 minutes | Checks the payment gateway and records the result for system health    |
| `qresto:check-integrations`        | every minute    | Flags integrations whose heartbeat went stale and alerts managers once |

Run by hand:

| Command                         | What it does                                                            |
| ------------------------------- | ----------------------------------------------------------------------- |
| `qresto:integration-token {id}` | Issues a new heartbeat token for a KDS screen, printer agent or gateway |
| `qresto:demo-refresh`           | Moves the demo restaurant's times up to now                             |

### Queue worker

Every notification is queued, so a void, a Stripe webhook or a health check returns before anyone is told (#56). With `QUEUE_CONNECTION=database` (the `.env.example` default) nothing is delivered until a worker runs. `composer dev` starts one; anywhere else, keep one running:

```bash
php artisan queue:work
```

## Checks before you push

CI runs all of these on every push to `dev` and `main` and on every pull request. Running them first saves a red build.

```bash
php vendor/bin/pint --test
php vendor/bin/phpstan analyse --memory-limit=512M
php artisan test --compact
npx eslint .
npm run lint:design
npm run format:check
npm run types:check
npm run test:components
```

Some guards worth knowing about, because they fail with a clear message rather than a bug:

- **Logical direction utilities.** `ml-`, `pr-`, `left-` and the like break the Arabic layout. Use `ms-`, `pe-`, `start-` and so on (`tests/Feature/LogicalUtilitiesTest.php`).
- **Restaurant scoping.** Any model whose table has `restaurant_id` must use `BelongsToRestaurant` (`tests/Feature/RestaurantScopeTest.php`).
- **Design non-negotiables.** 44px touch targets, contrast and the rest of the design's rules (`npm run lint:design`, #36).

## Branches

Work happens on `dev`; `main` is updated from `dev` through a pull request. Reference the issue number in each commit message, for example `fix(tenancy): … (#4)`.

## Design

The design files live in [`docs/design/`](docs/design/): foundations, the customer flow and the staff panels. Issues quote the section they implement, for example `01-foundations.dc.html §06 Bilingual`. How live data is refreshed is described in [`docs/design/live-data.md`](docs/design/live-data.md).
