# QResto design source (read-only)

Vendored from the Claude Design project `57914f0b-0695-40b2-bb49-2bda0ead629c`.
These files are the **specification**, not application code — nothing here is
compiled, imported or served by Laravel or Vite.

| File | What it holds |
| --- | --- |
| `01-foundations.dc.html` | Colour ramps, type scale, spacing/shape/elevation/motion, the component gallery, bilingual rules, and the seven non-negotiables |
| `02-customer-flow.dc.html` | The 7 guest screens (390×812) with a `SPEC` block per screen: intent, layout, components, data points |
| `03-staff-panels.dc.html` | The 8 staff screens across 4 roles and 3 widths, with `SPEC` blocks that also carry responsive behaviour, plus the role capability `MATRIX` |
| `ds/styles.css` + `ds/tokens/*.css` | The 203 design tokens, split by concern. `tokens/semantic.css` holds the aliases product code should reference |

The `SPEC` and `MATRIX` constants live in the `<script type="text/x-dc">` block at
the bottom of each artboard. To read one screen's spec:

```bash
sed -n '/^const SPEC/,/^};/p' docs/design/03-staff-panels.dc.html
```

The canvas runtime (`support.js`) is deliberately **not** vendored — it is a
template engine for the design tool and has no bearing on this application.

Do not edit these files. Update the design in Claude Design and re-export.
