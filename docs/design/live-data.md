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

Controllers expose the volatile slice as its own closure prop, which Inertia evaluates only when a partial reload asks for it:

'orders' => fn (): array => $this->orders($user),

The client polls with `only: \['orders']` (the pass and runner use `tickets` and `now`, the guest status screen uses `live`), so the rest of the page is never rebuilt on a poll.

Live props must not be wrapped in `Inertia::defer()` because they need to be available during partial reloads.

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
