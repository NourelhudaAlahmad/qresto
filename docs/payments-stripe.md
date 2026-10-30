# Payments with Stripe (#35)

QResto talks to payment gateways through `App\Payments\Contracts\PaymentGateway`.
Two drivers ship:

| Driver | Used for | Card details |
|---|---|---|
| `fake` | local work and **the whole test suite** (pinned in `phpunit.xml`) | typed into QResto inputs, never leave the test |
| `stripe` | test-mode and live payments | typed into Stripe Elements; only a `pm_…` id reaches QResto |

With `stripe`, the checkout **refuses** raw `card_number` / `card_expiry` / `card_cvc`
fields, so a card number can never reach our servers by accident.

## Switching to Stripe (test mode)

1. Install the SDK (once): `composer require stripe/stripe-php`
2. In `.env`, with keys from https://dashboard.stripe.com/test/apikeys:

   ```env
   QRESTO_PAYMENT_DRIVER=stripe
   STRIPE_KEY=pk_test_...
   STRIPE_SECRET=sk_test_...
   STRIPE_WEBHOOK_SECRET=whsec_...   # from `stripe listen`, below
   STRIPE_COUNTRY=US                 # for Apple Pay / Google Pay sheets
   ```

3. `php artisan config:clear`

## Webhooks locally

Install the Stripe CLI (https://docs.stripe.com/stripe-cli), then:

```bash
stripe login
stripe listen --forward-to http://qresto.test/webhooks/stripe
```

`stripe listen` prints a signing secret (`whsec_...`): put it in
`STRIPE_WEBHOOK_SECRET` and clear the config cache. Keep it running while you test.

The endpoint verifies every signature (and rejects payloads older than five
minutes), and records each event id in `webhook_events`: a replayed or retried
event is a no-op. It handles:

| Event | Effect |
|---|---|
| `payment_intent.succeeded` | settles the payment and the order (through `SettlePayment`) |
| `payment_intent.payment_failed` | marks the payment failed (never un-pays a settled one) |
| `charge.refunded` | applies a refund made in the Stripe dashboard |
| `charge.dispute.created` | order event + bell notification to whoever sees financial reports |

Replay one to check idempotency:

```bash
stripe events resend evt_...
```

## Test cards

| Card | Result |
|---|---|
| `4242 4242 4242 4242` | succeeds immediately |
| `4000 0027 6000 3184` | 3-D Secure challenge, shown **in the page** |
| `4000 0000 0000 0002` | declined, shown inline as "Your card was declined." |
| `4000 0000 0000 9995` | insufficient funds |

Any future expiry and any CVC.

## Apple Pay and Google Pay

The buttons appear only when the browser and device can pay with them
(Payment Request API). Apple Pay needs HTTPS and a verified domain in the Stripe
dashboard; on `http://qresto.test` expect only Google Pay in Chrome with a saved card.
