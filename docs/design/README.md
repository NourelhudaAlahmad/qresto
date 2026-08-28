# QResto design source (read-only)

Vendored from the Claude Design project `57914f0b-0695-40b2-bb49-2bda0ead629c`.
These files are the **specification**, not application code — nothing here is
compiled, imported or served by Laravel or Vite.

Open any `.dc.html` file directly in a browser. No build step, no dev server.

| File | What it holds |
| --- | --- |
| `01-foundations.dc.html` | Colour ramps, type scale, spacing/shape/elevation/motion, the component gallery, bilingual rules, and the seven non-negotiables |
| `02-customer-flow.dc.html` | The 7 guest screens (390×812) with a `SPEC` block per screen: intent, layout, components, data points |
| `03-staff-panels.dc.html` | The 8 staff screens across 4 roles and 3 widths, with `SPEC` blocks that also carry responsive behaviour, plus the role capability `MATRIX` |
| `ds/styles.css` + `ds/tokens/*.css` | The 203 design tokens, split by concern. `tokens/semantic.css` holds the aliases product code should reference |
| `support.js` | The canvas runtime that makes the pages interactive — see below |
| `vendor/dc-runtime.js` | The runtime exactly as Claude Design exports it, kept for rebuilds |
| `build-support.sh` | Regenerates `support.js` |

## The pages are interactive

`02` and `03` are not static mockups. They are canvas templates — `{{ }}`
bindings, `<sc-if>` / `<sc-for>` blocks, and a `class Component extends DCLogic`
script at the bottom of each file. Click through them:

- **Customer flow** — the seven screen tabs, and the screens themselves: filter
  the menu by category, configure a meal, change quantities, switch payment
  method (watch the CTA verb and the saffron banner change), tip, split the
  bill, advance the order status.
- **Staff panels** — switch role (waiter / manager / super admin / kitchen),
  switch viewport (desktop 1180 / tablet 834 / phone 390) to see the responsive
  behaviour each `SPEC` describes, advance orders through the kanban, open the
  void dialog, open the add-waiter drawer.
- **Foundations** — hover and press the components; the interactions are the
  spec, not a description of it.

The right-hand panel follows whatever screen you are on and shows that screen's
intent, layout, components and data points.

## Why `support.js` is a concatenation

The canvas runtime fetches React 18.3.1 and `@babel/standalone` from `unpkg.com`
at boot. That would make this documentation require a network connection and an
uncensored path to a CDN. `build-support.sh` inlines React ahead of the runtime,
so `loadReactUmd()` finds `window.React` already present and returns without a
request. Babel is only used for external JSX modules, which these artboards do
not have, so it is never fetched.

The result is one 210KB file and three pages that work from the filesystem.

To rebuild — only needed if Claude Design ships a new runtime, in which case
save it to `vendor/dc-runtime.js` first:

```bash
./docs/design/build-support.sh
```

**One remaining network dependency:** `ds/tokens/fonts.css` pulls Familjen
Grotesk, Outfit and JetBrains Mono from Google Fonts. Offline, the pages fall
back to Helvetica and system-ui — everything still works, the type is just not
the brand type. Self-hosting the binaries (all three are SIL Open Font License)
would close that gap.

## Reading a screen's spec without opening a browser

The `SPEC` and `MATRIX` constants live in the `<script type="text/x-dc">` block
at the bottom of each artboard:

```bash
sed -n '/^const SPEC/,/^};/p' docs/design/03-staff-panels.dc.html
```

## Editing

Do not hand-edit the artboards or the token CSS. Update the design in Claude
Design and re-export. The artboards are untouched from their export — the
standalone fix lives entirely in `support.js`, so re-exporting the HTML does not
undo it.
