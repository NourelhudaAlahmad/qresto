---
paths:
  - 'app/Models/**'
---

# Models

## PHPStan level 7 generics on Eloquent models
Larastan runs at level 7, so new models must carry generic annotations or `composer types:check` fails:
- `/** @use HasFactory<XFactory> */` above `use HasFactory;` (import the factory from `Database\Factories`).
- Every relation method needs `@return BelongsTo<Related, $this>` / `HasMany<Related, $this>` / `BelongsToMany<Related, $this>`; scopes need `@param Builder<$this>` and `@return Builder<$this>`; accessors need `@return Attribute<TGet, TSet>` (use `never` for TSet on read-only accessors).
- Inside traits that operate on a bare `Model` (e.g. `BelongsToRestaurant`), reach columns with `getAttribute()`/`setAttribute()` — direct `$model->column` is an undefined-property error on the base class.
- `phpstan.neon` sets `parseModelCastsMethod: true` so Larastan reads the body of `casts()`. Without it Larastan only looks at the method's declared `array` return type, ignores all casts, and types datetime/enum columns as raw DB strings.
