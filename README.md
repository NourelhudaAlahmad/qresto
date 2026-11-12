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
| `model:prune`                      | daily           | Deletes operational rows past their retention period (below)           |

Run by hand:

| Command                         | What it does                                                            |
| ------------------------------- | ----------------------------------------------------------------------- |
| `qresto:integration-token {id}` | Issues a new heartbeat token for a KDS screen, printer agent or gateway |
| `qresto:demo-refresh`           | Moves the demo restaurant's times up to now                             |

### Retention

`model:prune` deletes only rows that have outlived their use (#57). The periods are in `config('qresto.retention')`, each overridable by its env variable.

| Rows                                                | Kept for                                                                      | Why                                                                                  |
| --------------------------------------------------- | ----------------------------------------------------------------------------- | ------------------------------------------------------------------------------------ |
| Table sessions with no order                        | 30 days after the guest's last activity (`QRESTO_RETAIN_TABLE_SESSIONS_DAYS`) | A QR scan that never became an order. Its service requests stay, unlinked            |
| Carts, cart lines and add-ons                       | 7 days after the session's last activity (`QRESTO_RETAIN_CARTS_DAYS`)         | A cart is dead once its session is; orders copy what they need into `order_lines`    |
| Webhook events                                      | 30 days after receipt (`QRESTO_RETAIN_WEBHOOK_EVENTS_DAYS`)                   | A replay guard. Stripe retries for three days and keeps events resendable for thirty |
| Table sessions with an order                        | Indefinitely                                                                  | They hold the party size that covers are counted from in the overview and reports    |
| Orders, order lines, payments                       | Indefinitely                                                                  | The sales record                                                                     |
| `order_events`, `permission_audits`, `staff_audits` | Indefinitely                                                                  | The audit trail: who did what, and when                                              |

To see what a prune would delete without deleting it:

```bash
php artisan model:prune --pretend
```

### Queue worker

Every notification is queued, so a void, a Stripe webhook or a health check returns before anyone is told (#56). With `QUEUE_CONNECTION=database` (the `.env.example` default) nothing is delivered until a worker runs. `composer dev` starts one; anywhere else, keep one running:

```bash
php artisan queue:work
```

## Deploying to production

`.env.example` is set up for a laptop. Deployed as it is, it loses invitations, approves every card and queues a floor of polling screens behind one SQLite lock. This section goes from a bare server to a running restaurant. It does not pick a host for you.

### What a deploy runs

| Piece          | Why                                                                                                                         |
| -------------- | --------------------------------------------------------------------------------------------------------------------------- |
| Web server     | nginx or Apache with PHP-FPM, serving `public/` over HTTPS                                                                  |
| Database       | MySQL 8, MariaDB 10.6+ or PostgreSQL 14+. Not SQLite (below)                                                                |
| Queue worker   | `php artisan queue:work`, always running. Every notification goes through the queue (#56)                                   |
| Scheduler      | `php artisan schedule:run` every minute: closing idle sessions, health probes, integration checks and the daily prune (#57) |
| Mail           | An SMTP account or mail service. Staff invitations and password resets depend on it                                         |
| Stripe account | Live keys and a webhook endpoint, for card payments (#35)                                                                   |

The server needs PHP 8.4 with the extensions Laravel itself requires (`ctype`, `curl`, `dom`, `fileinfo`, `mbstring`, `openssl`, `pdo`, `tokenizer`, `xml`), plus `pdo_mysql` or `pdo_pgsql` for the database and `gd` for menu photos; Composer; and Node 22 to build the frontend.

### Drivers, and why

| Setting                         | Production value                           | Why                                                                                                                                                                                                 |
| ------------------------------- | ------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `DB_CONNECTION`                 | `mysql`, `mariadb` or `pgsql`              | Nine screens poll every 4–10 seconds and sessions, cache and jobs all write too. SQLite allows one writer at a time, so a manager, three waiters, a pass and a cashier end up waiting on each other |
| `SESSION_DRIVER`, `CACHE_STORE` | `database` is fine; `redis` if you have it | On a real database server these are cheap. Redis takes the load off it if the restaurant is busy                                                                                                    |
| `QUEUE_CONNECTION`              | `database` or `redis`                      | Either works, as long as a worker runs. `sync` would send every notification inside the request again                                                                                               |
| `MAIL_MAILER`                   | `smtp` (or `ses`, `postmark`, …)           | With `log`, an invitation is written to a file and the new waiter never gets it. **Mail is a prerequisite, not an extra**                                                                           |
| `BROADCAST_CONNECTION`          | `log`                                      | Nothing broadcasts yet. Screens poll (#11)                                                                                                                                                          |

### Environment checklist

Each of these must change from `.env.example` before going live. `php artisan qresto:check-deploy` checks the ones marked ✓ and exits non-zero until they are right. In production, the app **refuses to serve** (every request, `/up` included, returns an error and the log names the setting) while any of them is still wrong.

| Variable                                                                                       | Set to                                                                | Checked |
| ---------------------------------------------------------------------------------------------- | --------------------------------------------------------------------- | :-----: |
| `APP_NAME`                                                                                     | The restaurant or brand name, used in emails                          |         |
| `APP_ENV`                                                                                      | `production`                                                          |         |
| `APP_KEY`                                                                                      | `php artisan key:generate --show`, then keep it forever (see Backups) |         |
| `APP_DEBUG`                                                                                    | `false`                                                               |    ✓    |
| `APP_URL`                                                                                      | `https://your-domain`. Table QR codes are printed with it             |    ✓    |
| `LOG_LEVEL`                                                                                    | `warning` or `error`                                                  |         |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`             | Your MySQL, MariaDB or PostgreSQL server                              |    ✓    |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` | Your mail service                                                     |    ✓    |
| `QRESTO_PAYMENT_DRIVER`                                                                        | `stripe`                                                              |    ✓    |
| `STRIPE_KEY`, `STRIPE_SECRET`                                                                  | Live keys, `pk_live_…` and `sk_live_…`                                |    ✓    |
| `STRIPE_WEBHOOK_SECRET`                                                                        | The endpoint's signing secret, `whsec_…` (below)                      |    ✓    |
| `STRIPE_COUNTRY`                                                                               | The country of the Stripe account, for Apple Pay and Google Pay       |         |
| `QRESTO_CURRENCY`                                                                              | The restaurant's ISO currency                                         |         |
| `SEED_DEMO_DATA`                                                                               | `false`                                                               |         |

`QRESTO_ENFORCE_DEPLOY_CHECKS=false` turns the refusal off. It exists for a deliberate exception, such as a single-tablet demo, not for getting a deploy past the check.

To see the whole configuration at a glance, Laravel's drivers and a QResto section with the production checks, the payment driver, the Stripe mode (test or live) and the retention periods:

```bash
php artisan about
```

### First install

On the server, as the user that runs PHP:

```bash
git clone <repository> qresto && cd qresto
```

```bash
composer install --no-dev --optimize-autoloader
```

```bash
npm ci && npm run build
```

```bash
cp .env.example .env && php artisan key:generate
```

Edit `.env` through the checklist above, then:

```bash
php artisan qresto:check-deploy
```

```bash
php artisan migrate --force && php artisan db:seed --force
```

```bash
php artisan storage:link
```

```bash
php artisan optimize
```

`db:seed` creates the roles and permissions only. With `SEED_DEMO_DATA=false` it adds no demo restaurant. Create the real one and invite its first admin:

```bash
php artisan qresto:create-restaurant "Lokanta Deniz" --admin-name="Selin Kaya" --admin-email=selin@example.com --timezone=Europe/Istanbul --currency=TRY
```

The invitation is sent straight away, not queued, so a mail problem shows up here. The admin sets a password from the link, then adds the menu, tables and staff from the app.

Point the web server's document root at `public/`.

### Keeping the worker and scheduler running

Both must survive reboots and crashes. With systemd, a worker unit (`/etc/systemd/system/qresto-worker.service`):

```ini
[Unit]
Description=QResto queue worker
After=network.target

[Service]
User=www-data
WorkingDirectory=/var/www/qresto
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable --now qresto-worker
```

And the scheduler, in the crontab of the same user (`crontab -e -u www-data`):

```
* * * * * cd /var/www/qresto && php artisan schedule:run >> /dev/null 2>&1
```

Supervisor works just as well for the worker. Whatever runs it, check that it is up: `php artisan schedule:list` shows what will run, and jobs piling up in the `jobs` table mean no worker is running.

### Stripe webhooks

In the Stripe dashboard (Developers → Webhooks), add an endpoint:

- URL: `https://your-domain/webhooks/stripe`
- Events: `payment_intent.succeeded`, `payment_intent.payment_failed`, `charge.refunded`, `charge.dispute.created`

Copy its signing secret into `STRIPE_WEBHOOK_SECRET`. Test and live mode have separate keys **and separate endpoints**: switching to live means live `pk_`/`sk_` keys and the live endpoint's `whsec_` secret together. `php artisan about` shows which mode the secret key is in. Local testing with the Stripe CLI is in [`docs/payments-stripe.md`](docs/payments-stripe.md).

### Updating

```bash
php artisan down
```

```bash
git pull && composer install --no-dev --optimize-autoloader && npm ci && npm run build
```

```bash
php artisan migrate --force && php artisan optimize
```

```bash
php artisan queue:restart && php artisan up
```

`queue:restart` makes the worker finish its job and start again on the new code.

### Backups

What to keep:

- **The database.** Orders, payments, the `order_events` and `permission_audits` audit trail, staff and the menu. Everything that matters.
- **`.env`**, above all `APP_KEY`. Two-factor secrets and session cookies are encrypted with it. A restored database without its key cannot read them.
- **`storage/app/public`**: the menu photos.

A nightly database dump, kept off the server. MySQL or MariaDB:

```bash
mysqldump --single-transaction --routines --default-character-set=utf8mb4 -u qresto -p qresto | gzip > qresto-$(date +%F).sql.gz
```

PostgreSQL:

```bash
pg_dump -Fc -U qresto qresto > qresto-$(date +%F).dump
```

`--single-transaction` and `pg_dump` both take a consistent snapshot without stopping the service.

### Restoring, and checking the restore

A backup nobody has restored is a hope. Restore into a **new, empty** database first, never over the live one:

```bash
gunzip -c qresto-2026-11-12.sql.gz | mysql -u qresto -p qresto_restore
```

```bash
pg_restore -U qresto -d qresto_restore --no-owner qresto-2026-11-12.dump
```

Then, from a copy of the app with `DB_DATABASE=qresto_restore` and the **same** `APP_KEY`:

1. `php artisan migrate:status` lists every migration as ran, with none pending.
2. `php artisan about` shows the restore's connection and no surprises.
3. The newest order in the restore matches the newest one from before the dump: `php artisan tinker --execute 'echo App\Models\Order::query()->withoutGlobalScopes()->latest("id")->value("code");'`
4. Sign in as a staff member and open a past order and its receipt.

Only then point the live `.env` at the restored database (or rename it into place), clear the config cache with `php artisan optimize`, and restart the worker.

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
