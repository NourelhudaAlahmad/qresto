---
paths:
  - 'app/Actions/Orders/**'
---

# Orders

## Guests may order outside opening hours
Settled product decision (2026-11-21): restaurant hours (Restaurant::isOpenAt, open_now on the landing page) are display-only. Checkout and PlaceOrder deliberately do not refuse orders while the restaurant is closed; staff decide. Do not add an opening-hours check to ordering without the owner's say-so.
