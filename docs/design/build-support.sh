#!/usr/bin/env bash
#
# Rebuild docs/design/support.js — the standalone canvas runtime.
#
# The artboards are Claude Design canvas templates: 02 and 03 carry {{ }}
# bindings, <sc-if>/<sc-for> blocks and a `class Component extends DCLogic`
# script that only render once the canvas runtime has expanded them. That
# runtime fetches React from unpkg at boot, which would make these pages
# require a network connection. This script inlines React ahead of it so the
# pages open straight from the filesystem.
#
# Re-run after re-exporting the design, only if the runtime itself changed:
#   ./docs/design/build-support.sh
#
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REACT_VERSION="18.3.1"
RUNTIME="$HERE/vendor/dc-runtime.js"
OUT="$HERE/support.js"

[[ -f "$RUNTIME" ]] || {
  echo "Missing $RUNTIME — export support.js from the Claude Design project" >&2
  echo "(project 57914f0b-0695-40b2-bb49-2bda0ead629c) and save it there." >&2
  exit 1
}

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

echo "Fetching react@$REACT_VERSION UMD builds..."
curl -sSfL -o "$TMP/react.js" \
  "https://unpkg.com/react@$REACT_VERSION/umd/react.production.min.js"
curl -sSfL -o "$TMP/react-dom.js" \
  "https://unpkg.com/react-dom@$REACT_VERSION/umd/react-dom.production.min.js"

{
  cat <<HDR
/* QResto design system — standalone canvas runtime.
 *
 * Concatenation of three files, in this order:
 *   1. react@$REACT_VERSION        umd/react.production.min.js
 *   2. react-dom@$REACT_VERSION    umd/react-dom.production.min.js
 *   3. the Claude Design canvas runtime (dc-runtime), exported with the artboards
 *
 * The canvas runtime normally fetches React $REACT_VERSION and @babel/standalone from
 * unpkg.com at boot. React is inlined above it here so that loadReactUmd()
 * finds window.React / window.ReactDOM already present and returns without a
 * network request — which is what lets these pages open from the filesystem,
 * offline, with no build step. Babel is only used for external JSX modules;
 * these artboards have none, so it is never requested.
 *
 * Do not hand-edit. Rebuild with docs/design/build-support.sh.
 */
HDR
  echo "/* ---- react@$REACT_VERSION (UMD, production) ---- */"
  cat "$TMP/react.js"; echo ";"
  echo "/* ---- react-dom@$REACT_VERSION (UMD, production) ---- */"
  cat "$TMP/react-dom.js"; echo ";"
  echo "/* ---- Claude Design canvas runtime ---- */"
  cat "$RUNTIME"
} > "$OUT"

echo "Wrote $OUT ($(wc -c < "$OUT" | tr -d ' ') bytes)"
command -v node >/dev/null && node --check "$OUT" && echo "Parses clean."
