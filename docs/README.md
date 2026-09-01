# docs

Start here. Read the file that matches what you are about to touch — not all of them.

## Read before you edit

| Task | Read |
| --- | --- |
| **Anything at all, first thing in a session** | [`agents/state-of-play.md`](agents/state-of-play.md) — what is done, what is uncommitted, what is known-broken |
| Changing admin dashboard styling | [`agents/admin-ui.md`](agents/admin-ui.md) |
| Changing storefront styling | [`agents/storefront-ui.md`](agents/storefront-ui.md) |
| Filing or working an issue | [`agents/issue-tracker.md`](agents/issue-tracker.md) |
| Domain terms and decisions | [`agents/domain.md`](agents/domain.md) |

`agents/admin-ui.md` and `agents/storefront-ui.md` are the **architecture maps** — class
hooks, scoping rules, and the named traps that make one bug look like fifty. The storefront
one assumes you have read the admin one; the shared CSS rules are documented there.

## History (read only if you need the "why")

| File | What it covers |
| --- | --- |
| [`admin-design-polish.md`](admin-design-polish.md) | The dashboard polish pass — the rem/px scale bug, the theme swap, the status-pill palette, help hints |
| [`storefront-antd-migration.md`](storefront-antd-migration.md) | The storefront's move to Ant Design — what moved, the dead shadcn tree that came out, the light-mode palette |

These are change records, not references. If you want to know *how something works*, use the
architecture maps above. If you want to know *why it is like that*, come here.

## The two rules that keep biting

1. **rem vs px.** Tailwind utilities are rem, Ant Design tokens are px, and the app scales
   its root font. Both shells pin the root back to 16px for exactly this reason. If a size
   looks off by roughly a quarter, that's the cause.
2. **`html`-rooted selectors.** `.dark` and `dir` live on `<html>`, so they must be
   *compounded* onto it (`html.dark:has(…)`), never used as an ancestor
   (`.dark html:has(…)` matches nothing, silently). Both maps cover it.
