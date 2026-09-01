## Platform

E-commerce store. Backend: Laravel + Inertia.js. Frontend: Vue 3 + TypeScript + Tailwind (shadcn/radix-vue style components). Auth: Sanctum + Socialite (social login), roles/permissions via `spatie/laravel-permission`, activity logging via `spatie/laravel-activitylog`. Payments: no gateway — Cash on Delivery and Bank Transfer only (`App\Enums\PaymentMethod`), admin manually confirms payment (`App\Actions\Payments\ConfirmPayment`). Multi-locale with RTL support; currency is Libyan Dinar (LYD).

## High-risk zones — explicit approval required

Don't modify these without confirming with the user first:
- `app/Http/Middleware/` (esp. `EnsureUserIsActive`, `SetLocale`)
- Anything under `app/Actions/Payments/`, `app/Actions/Checkout/`, `app/Actions/Returns/` (refunds)
- `app/Http/Controllers/Auth/`, Sanctum/Socialite config
- Role/permission logic (`RoleController`, spatie permission seeders)

## Execution limits

- Bug fixes: keep the diff small, one fix per commit. If a "fix" is touching 5+ files, stop and confirm scope with the user first.
- Never claim a task is done without running the relevant verify command and showing its output (see Verification).

## Verification

Run before claiming done:
- PHP changes: `./vendor/bin/pint --dirty` (format) and `./vendor/bin/pest` (or a scoped `--filter`) for touched areas.
- Frontend/TS changes: `npm run lint` and `vue-tsc --noEmit` if available in `package.json` scripts.
- CI mirrors this: `.github/workflows/lint.yml` and `.github/workflows/tests.yml`.

## Environment & secrets

- `.env` files are permission-blocked from Claude's Read/Bash tools in this session — don't try to work around that.
- Never `git add` `.env`, credentials, or generated secrets.

## Money & dates — use the existing helpers

- Currency: always format via `App\Support\Money` (LYD, 3 fraction digits, amount-first). Never hand-roll `number_format` for prices.
- Dates: always format via `App\Support\Dates`. App timezone is UTC — don't assume local time in raw DB timestamps.

## Docs

`docs/README.md` is the index — it says which file to read for which task. Start a session
with `docs/agents/state-of-play.md`: branch state, what is uncommitted, known issues that
are not worth re-investigating, and things that were deliberately left alone.

## Agent skills

### Issue tracker

Issues live as GitHub Issues in MahmoudShnnib/electronic-store, managed via the `gh` CLI. See `docs/agents/issue-tracker.md`.

### Domain docs

Single-context: `CONTEXT.md` + `docs/adr/` at the repo root (created lazily as terms/decisions resolve). See `docs/agents/domain.md`.

### UI

Both halves of the app are Ant Design Vue over Tailwind. Read `docs/agents/admin-ui.md` before changing admin styling and `docs/agents/storefront-ui.md` before changing the shop — between them they map the two themes, the shared components, the CSS rules deliberately shared by both shells, and the global AntD ⇄ Tailwind conflicts that make one bug look like fifty.
