# Architecture / boundaries

## Core vs adapters

- **Core**: semantic email-safe HTML patterns (tables with `role=presentation`), documented class names, a single CSS source and standalone rendered examples.
- **Demo adapter**: minimal Twig templates and fixtures in this prototype. Twig is not a requirement for consuming final HTML/CSS patterns.
- **Application**: owns translations, mail subjects, recipient preference, per-tenant identity, links, scheduling, delivery and message content.

## Component catalog (v0.1)

- `button` — principal / secondary; HTML table shape intended to survive Outlook.
- `actions` — optional second, short CTA; full-width and stacked below 620px.
- `subcopy` — visible fallback URL after a primary action, for forwarded or text-only views.
- `details` — labeled key/value rows (appointment, invitation).
- `notice` — information/warning; meaning always in the text, not color alone.
- `code` — large copyable authentication code.
- `digest_list` — repeating time + description lines.
- `spacer` — explicit allowed vertical spacings (16, 20, 28).

## CSS and compatibility

- Source CSS is `src/templates/styles/email.css`. Export passes it to the Twig `inline_css` filter, applied to the **full HTML document**; the layout's media queries and client resets stay in `<head>`.
- Mobile padding, title size and action stacking live in a `<style>` media query. The stacking uses `display:block` on the action cells: clients that honour media queries stack, the rest keep the desktop row.
- One 600px column, fluid outside; an Outlook conditional wrapper pins desktop width. This is a *prototype*, not a fully tested Outlook VML implementation.
- Email typography falls back to Arial/Helvetica; there are no external fonts or images to block. `word-break` is inherited from `body` so long names and URLs wrap.
- `format-detection` and an `a[x-apple-data-detectors]` reset stop iOS from restyling dates and addresses.
- Dark mode is a best-effort light/dark pair: `--dark-*` tokens feed a `prefers-color-scheme` block. Client-driven inversion can still override it; test before promising a specific theme.
- Buttons include background on their table cell, but Word-based Outlook differences may need further testing.
- Border radius, letter spacing and some colors may degrade in individual clients without losing information.
- Do not interpret a Chromium screenshot as evidence that the email renders well in Outlook/Gmail.

## Integrating in a host application

1. Keep the host application's delivery pipeline (intent → composer → transport) unchanged.
2. Copy `layout`, `layout.txt`, CSS (with its `:root` theme tokens), `src/theme.php` and only the used components into the host application's email template directory; resolve tokens before `inline_css`.
3. Keep one message template per delivery intent, existing i18n catalogs, fixed locale and absolute URLs. Move the layout chrome strings (logo mark, help label, footer note, website label) into the intent payload or the Twig globals instead of editing markup.
4. Add `twig/cssinliner-extra` and `twig/extra-bundle` in the Symfony project if needed; test real compiled HTML.
5. Adapt the host project's template checks (class discovery, inline-style rules) so classes in the email CSS are discoverable. Do **not** add global exceptions allowing handcrafted inline styles, and check that classes built from variables (`email-space-{{ size }}`) are still recognised.
6. Preserve the host application's fixture/tests contract: every message type in every supported locale with `strict_variables`.
7. Pair each HTML message with a meaningful text version that extends `layout.txt` and keeps exactly the same links.

## Scope exclusion

No sending, no HTML editor, no MJML, no full layout grid, no automatic digest of sensitive data, no real business-domain logic, no i18n catalog duplication, no opinionated Symfony bundle.
