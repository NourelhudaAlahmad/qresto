\# QResto Live Data Convention

QResto uses Inertia v3 polling for live operational screens instead of websockets.

\## Client convention

Live screens use the shared `useLiveData(intervalMs, { only: \[...] })` hook.

The hook:

\- polls through Inertia `usePoll`

\- requests only the volatile prop listed in `only`

\- exposes pause and resume controls

\- pauses polling while the browser tab is hidden

\- resumes immediately when the tab becomes visible

\## Polling intervals

The default operational intervals are defined in `config/qresto.php`:

\- Waiter: 5 seconds

\- Floor: 8 seconds

\- KDS: 4 seconds

\- Guest status: 10 seconds

\## Server contract

Controllers expose live data as a dedicated prop:

'liveOrders' => LiveResponse::make($orders),

`LiveResponse` returns:

\* `items`: the volatile collection

\* `version`: the maximum `updated\_at` value plus the collection count

Live props must not be wrapped in `Inertia::defer()` because they need to be available during partial reloads.

When a client sends an unchanged live-data version, the controller may short-circuit with an empty response instead of serializing the collection again.

\## New-item alerts

New items use `useNewItemAlert(collection, keyFn)`.

A previously unseen key triggers:

\* a non-blocking toast

\* a short notification sound

Alerts respect:

\* `prefers-reduced-motion`

\* the user's mute preference

The mute preference is stored locally in the browser.

\## Status transitions

Status changes use the shared `StatusTransition` wrapper and a fade transition rather than a directional slide.

\## Out of scope

Realtime websockets and Reverb are intentionally out of scope for this convention.
