# Ta’aleem design system

Extracted from the TaaleemNG school app so other projects can reuse the same UI language without copying the whole Laravel codebase.

| Artifact | Path |
| --- | --- |
| **Portable kit (all-in-one)** | [`design/kit/`](design/kit/) — DESIGN.md + theme.css + components copy |
| Tokens + base styles | [`design/theme.css`](design/theme.css) |
| Live app CSS (Laravel-specific sources) | [`resources/css/app.css`](resources/css/app.css) |
| Blade primitives (live) | [`resources/views/components/`](resources/views/components/) |


Stack in this repo: **Tailwind CSS v4**, **Alpine.js**, **Lucide** (`mallardduck/blade-lucide-icons`), **GSAP**, custom Blade components. Not Bootstrap / DaisyUI / shadcn / Filament.

---

## Quick start (another Vite + Tailwind v4 app)

1. Install Tailwind v4 + Vite plugin as usual.
2. Copy `design/theme.css` into the new project (e.g. `src/theme.css`).
3. In your main CSS:

```css
@import 'tailwindcss';
@import './theme.css';
```

4. Load **Outfit** (Google Fonts or `@fontsource/outfit`).
5. Optionally copy Blade (or port) components listed below.
6. For icons, use Lucide (Blade package or `lucide` npm).

To re-skin: change `--color-brand` (and related brand steps) in `@theme`. Keep structure classes the same.

---

## Design principles

1. **Calm product UI** — light canvas, soft borders (`border-line`), white cards, generous radius (`rounded-2xl` / `rounded-3xl`). Avoid harsh multi-layer shadows and glow.
2. **One primary accent** — periwinkle `brand`. Use `ink` for authority (headings, default CTAs), not black `#000`.
3. **Semantic colour, not decoration** — `naija` = success / money-in; `gold` = caution / “not taken”; `sky` = info; `danger` = destructive / absent.
4. **Typography does hierarchy** — Outfit everywhere. Kickers are small uppercase brand labels; page titles are bold with tight tracking; body sits at ~15px muted when secondary.
5. **Components over one-off cards** — prefer shared button / field / card / modal / drawer patterns so density stays consistent.
6. **Avoid default AI looks** — no Inter/Roboto-only stacks, no purple-on-white SaaS cliché as the whole theme, no warm-cream + terracotta editorial default unless intentional.

---

## Colour tokens

| Token | Hex / notes | Use |
| --- | --- | --- |
| `brand` / `brand-500` | `#7476f1` | Primary accent, links, focus |
| `brand-600` | `#5a5cd6` | Text on light bg, stronger CTA |
| `brand-50` / `100` | tinted mixes | Soft fills, icon wells |
| `ink` | `#101828` | Body text, headings, dark buttons |
| `canvas` | `#fafbff` | Page background |
| `surface` | `#fbfcff` | Alternate surface |
| `chip` | `#f1f2fa` | Ghost hover, pill neutrals |
| `line` | `#e4e7f2` | Borders |
| `muted` | `#566074` | Secondary text |
| `placeholder` | `#7a8296` | Input placeholders |
| `naija` | `#0b7a4b` | Success, present, credit |
| `gold` | `#7a5600` | Warning, late, pending |
| `sky` | `#2b5592` | Info, excused |
| `danger` | `#b42318` | Errors, delete, absent |

Utility examples: `bg-canvas`, `text-ink`, `border-line`, `bg-brand-50`, `text-brand-600`, `bg-naija-50`, `text-danger`.

---

## Typography

- **Font:** Outfit (sans).
- **Page title:** ~28–32px, `font-bold`, `tracking-[-0.03em]`.
- **Kicker:** `.kicker` — 13px, semibold, uppercase, `tracking-kicker`, `text-brand-600`.
- **Display (marketing):** `.display-2` — fluid clamp 34–56px, tight leading.
- **Body / UI:** 15px common for descriptions and table cells; `text-sm` / `text-xs` for meta.
- **Mono:** admission numbers, receipts — `font-mono text-xs text-muted`.

---

## Radius, shadow, spacing

| Token | Value | Typical use |
| --- | --- | --- |
| `rounded-xl` | 0.875rem | Inputs, small controls |
| `rounded-2xl` | 1.125rem | Cards, empty-state icon wells |
| `rounded-3xl` | 1.5rem | Modals, large panels, hero blocks |
| `shadow-card` | soft violet-tinted | Hover elevation on clickable stats |
| `shadow-panel` | deeper | Drawers / dialogs |
| `shadow-float` | marketing float | Landing accents |

Page shell content: `max-w-[1280px]`, `gap-6`, horizontal padding `px-4 sm:px-6`.

---

## Component inventory (this repo)

Copy from `resources/views/components/`:

| Component | Role |
| --- | --- |
| `button` | Variants: `primary` (ink), `brand`, `secondary`, `soft`, `ghost`, `danger`. Sizes `sm` / `md` / `lg`. Optional Lucide `icon`. |
| `card` | White, `rounded-2xl`, `border-line`; optional padding. |
| `field` / `select-field` | Form controls using `.field`. |
| `badge` | Tones: brand, naija/success, gold/warning, danger, sky, ink, neutral. |
| `page-header` | Kicker + title + description + `actions` slot. |
| `stat-card` | Dashboard metric; tones brand/naija/gold/sky/danger. |
| `empty-state` | Icon well + title + body + slot CTA. |
| `modal` / `drawer` | Alpine `open-modal` / `open-drawer` events. |
| `confirm-dialog` | App-wide confirm; forms use `data-confirm="…"`. |
| `tabs` / `tab` | Hash-aware Alpine tabs. |
| `flash` | Session flash banners. |

### Button recipe (non-Blade)

```html
<!-- brand -->
<button class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-[15px] font-semibold bg-brand-600 text-white hover:bg-ink">Save</button>
<!-- secondary -->
<button class="inline-flex ... rounded-xl border border-line bg-white text-ink hover:border-brand hover:text-brand-600">Cancel</button>
<!-- ghost -->
<button class="inline-flex ... text-muted hover:bg-chip hover:text-ink">More</button>
```

### Card recipe

```html
<div class="rounded-2xl border border-line bg-white p-5 sm:p-6">…</div>
```

---

## Layout patterns

- **App shell:** fixed sidebar (~272px, rail ~76px), main column with page header then sections/cards.
- **Office tables:** full-width, `border-line` rows, uppercase muted column headers, mono for IDs.
- **Registers / lists:** divide-y rows, status as coloured letter buttons or badges.
- **Confirmations:** never browser `confirm()` — use the in-app confirm dialog.
- **Drawers:** right slide-over for create/edit; modals for short confirms.

---

## Motion

GSAP helpers (see `resources/js/motion.js`):

| Attribute | Behaviour |
| --- | --- |
| `data-enter` | Children fade/slide up on load (page headers). |
| `data-reveal` | Fade up when scrolled into view. |
| `data-stagger` | Cascade children (dashboard grids). |
| `data-float` | Slow idle bob (marketing). |

Respect `prefers-reduced-motion`. Alpine handles UI state (drawers, tabs, registers).

---

## Tone mapping (domain → colour)

Useful when porting school-adjacent or ops UIs:

| Meaning | Tone |
| --- | --- |
| Present / paid / success | `naija` |
| Late / not taken / warning | `gold` |
| Absent / void / delete | `danger` |
| Excused / info | `sky` |
| Primary action / brand | `brand` |
| Neutral meta | `muted` + `chip` |

---

## Do / don’t

**Do**

- Use `brand-600` for interactive text on light backgrounds.
- Prefer one primary CTA per header action group.
- Keep empty states with icon + title + one sentence + optional CTA.

**Don’t**

- Flat pure-white page with grey system fonts only.
- Stack multiple competing accent colours in one section.
- Put dense marketing stats in the first viewport of a product screen unless it’s a dashboard.
- Rely on browser alert/confirm dialogs.

---

## Laravel-specific notes

`resources/css/app.css` also includes `@source` paths and a `rail` variant for the sidebar. Those stay in the app; `design/theme.css` is the portable subset.

When regenerating this pack from the app, sync tokens from the `@theme` block in `resources/css/app.css` into `design/theme.css`.
