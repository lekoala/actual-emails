# Architecture / boundaries

## Core vs adapters

- **Core**: semantic email-safe HTML patterns (tables with `role=presentation`), documented class names, a single CSS source and standalone rendered examples.
- **Demo adapter**: minimal Twig templates and fixtures in this repository. Twig is not a requirement for consuming final HTML/CSS patterns.
- **Application**: owns translations, mail subjects, recipient preference, per-tenant identity, links, scheduling, delivery and message content.

## Component catalog (v0.1)

- `button` — principal / secondary; HTML table shape intended to survive Outlook.
- `actions` — optional second, short CTA; full-width and stacked below 620px.
- `subcopy` — visible fallback URL after a primary action, for forwarded or text-only views. Both `url` and translated `label` are required; there is no English default.
- `details` — labeled key/value rows (appointment, invitation).
- `notice` — information/warning; meaning always in the text, not color alone.
- `code` — large copyable authentication code.
- `digest_list` — repeating description lines; `title` is required, `time` and `subtitle` are optional. The time column is omitted when no row has a non-empty time; mixed lists keep an empty time cell on untimed rows so all descriptions align.
- `spacer` — explicit allowed vertical spacings (16, 20, 28).

## CSS and compatibility

- Source CSS is `src/templates/styles/email.css`. Export passes it to the Twig `inline_css` filter, applied to the **full HTML document**; the layout's media queries and client resets stay in `<head>`.
- Mobile padding, title size and action stacking live in a `<style>` media query. The stacking uses `display:block` on the action cells: clients that honour media queries stack, the rest keep the desktop row.
- One 600px column, fluid outside; an Outlook conditional wrapper pins card and footer to the desktop width. There is no VML: rounded corners degrade to square in Word-based Outlook.
- Email typography falls back to Arial/Helvetica; there are no external fonts or images to block. `word-break` is inherited from `body` so long names and URLs wrap.
- `format-detection` and an `a[x-apple-data-detectors]` reset stop iOS from restyling dates and addresses.
- Dark mode is a best-effort light/dark pair: `--dark-*` tokens feed a `prefers-color-scheme` block. Client-driven inversion can still override it; test before promising a specific theme.
- Buttons include background on their table cell; checked in Outlook 2019 (square corners, correct padding).
- Word-based Outlook rules learned from real renders:
  - Never place two tables directly next to each other: Word merges them, and the merged rows inherit a neighbour's background and borders. Vertical gaps use the `spacer` component, a `div` with `mso-line-height-rule: exactly` and a line-height/font-size equal to its height (a `font-size: 0` spacer collapses).
  - Spacing on a `div` uses `margin`, never `padding`: Word ignores `div` padding. Padding belongs on `td`.
- Border radius, letter spacing and some colors may degrade in individual clients without losing information.
- Do not interpret a Chromium screenshot as evidence that the email renders well in Outlook/Gmail.

## Integrating in a host application

1. Keep the host application's delivery pipeline (intent → composer → transport) unchanged.
2. Copy `layout`, `layout.txt`, CSS (with its `:root` theme tokens), `src/theme.php` and only the used components into the host application's email template directory; resolve tokens before `inline_css`.
3. Keep one message template per delivery intent, existing i18n catalogs, fixed locale and absolute URLs. Move the layout chrome strings (logo mark, help label, fallback-link label, footer note, website label) into the intent payload or the Twig globals instead of editing markup. Pass the translated fallback sentence as `subcopy.label` explicitly; the demo uses `brand.fallback_link_label`. Other English copy in the example messages is demonstration content to replace with the host's existing translations.
4. Add `twig/cssinliner-extra` and `twig/extra-bundle` in the Symfony project if needed; test real compiled HTML.
5. Adapt the host project's template checks (class discovery, inline-style rules) so classes in the email CSS are discoverable, including CSS stored under `templates/` rather than `assets/`. Do **not** add global exceptions allowing handcrafted inline styles, and check that classes built from variables (`email-space-{{ size }}`) are still recognised.
6. Preserve the host application's fixture/tests contract: every message type in every supported locale with `strict_variables`.
7. Pair each HTML message with a meaningful text version that extends `layout.txt` and keeps exactly the same links. The text layout contains the body and footer only: the host still supplies the subject to the transport's `Subject` header. Put each URL on its own line below its label.

## Integration feedback and adaptation choices

The first host integration reported needs beyond the demo defaults. The following boundaries apply when copying the kit:

- **Inlining**: the kit continues to export with `inline_css` on the whole document. A host that already uses `TijsVerkoyen\CssToInlineStyles` directly can use the same underlying engine without installing a Twig filter or Symfony bundle. After rendering with filename-based autoescape and strict variables, call `$inliner->convert($html, $theme['css'])` on the complete document, never a fragment. Preserve the layout's `<head>` rules and run equivalent render checks; this does not require marking user text `raw`.
- **Theme resolution**: resolve the stylesheet once per theme/version, rather than on every send. The demo resolver also enforces the no-literal-hex rule; a host may move that check to its stylesheet/build tests, while retaining unknown-token failures and keeping cached CSS and Twig tokens from the same theme version.
- **Light-only hosts**: there is currently no automatic light-only mode. In a copied layout, set both color-scheme meta tags and both `:root` scheme declarations to `light`, and remove the `prefers-color-scheme: dark` block together with any unused `--dark-*` tokens. Removing tokens alone fails with strict variables because the layout still reads them. A future kit option should distinguish a deliberately absent dark palette from an incomplete palette and test both, rather than silently accept missing colours. A light declaration cannot promise to prevent client-driven inversion.
- **Brand images**: the kit keeps the text mark and no remote image dependencies. A hosted-logo API is deferred because it conflicts with that contract. The integrating host owns any image policy it introduces, including URL longevity and fallback text. The pilot used a PNG at twice its displayed size, explicit HTML `width`/`height`, a non-empty brand-name `alt`, and a light background for a dark-lettered logo. These are integration choices to validate with blocked images and forced dark mode, not a kit compatibility guarantee. Do not assume that `alt` reproduces a text header, or that an unversioned URL alone guarantees long-term availability.
- **Button and fallback**: keep `button` and `subcopy` separate for now; a combined `fallback` option needs another concrete consumer before becoming an abstraction.
- **Outlook thin lines**: the pilot's cell-background workaround has no Outlook 2019 validation yet. Keep it as a candidate in [next-steps.md](next-steps.md), including matching corner radii and dark overrides; browser screenshots do not close this issue.

Migration for existing callers: supply `label` on every `subcopy` include (including calls with `with_context = false`). The digest change accepts existing timed rows unchanged. Plain-text exports no longer repeat the subject, and URLs now occupy separate lines.

## Scope exclusion

No sending, no HTML editor, no MJML, no full layout grid, no automatic digest of sensitive data, no real business-domain logic, no i18n catalog duplication, no opinionated Symfony bundle.
