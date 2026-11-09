# Actual Emails · 0.1

A small transactional email design kit, inspired by the philosophy of Actual CSS: content-first, clean defaults, just enough components and no frontend build chain. The output HTML is **standalone**; the included Twig renderer is only the authoring workflow.

## Open the preview

1. Open **`preview/index.html`** in a browser. Select one of the five example messages, switch between the three themes (rounded, sharp, soft) and between the desktop and mobile viewports.
2. Open **`dist/*.html`** to see finished emails (`dist/<message>--<theme>.html` for the other themes), or **`dist/*.txt`** for their plain-text counterparts.

All demonstration data is fictitious and URLs point to `example.org`; they are intentionally non-functional. The preview loads no external assets: the invitation's demo logo is a local file (`src/assets/logo.png`, copied to `dist/assets/` by the build). It is a development tool only: browser rendering is not validation in Gmail, Apple Mail or Outlook.

## Rebuild (PHP 8.2+ and Composer)

```sh
composer install
composer build   # render dist/*.html (every theme) and dist/*.txt
composer test    # fail if dist/ is stale, then run the output smoke test
```

No Symfony app, Node process or `package.json` is needed. `dist/` is generated and committed only so the gallery works **without installing anything**: never edit it by hand, rebuild it instead. `composer test` (via `php bin/build.php --check`) fails when `dist/` no longer matches the sources, then lints the source stylesheet and every theme, runs the output smoke test (structure, inlined styles, class/stylesheet drift, HTML/plain-text link parity) a WCAG AA contrast check on every palette, and a render test. The render test exercises the inliner itself: character-entity fidelity (the preheader filler depends on it), media-query preservation, the conditional Outlook wrapper width, agreement between Outlook's `bgcolor` attribute and the inlined `background-color`, no `padding` on `div`/`p`, a 20 KB per-message budget (all checks run in every theme), themes that only redefine existing tokens, every border radius coming from a token, and both header branches (text mark, or logo with HTML dimensions, empty `alt` and the brand name still as text). The smoke test also requires every demo image to be a local file in `dist/` with `width`, `height` and `alt`. The stylesheet lint rejects `!important`, short or uppercase hex, colour keywords, id selectors, duplicate properties and remote assets in `email.css` and in themes; a theme may contain nothing but its `:root` block.

`composer build` also removes obsolete `.html` and `.txt` exports from `dist/` after a message is removed or renamed. `--check` reports those files without deleting them; other file types are left untouched.

## Project layout

| Path | Role |
| --- | --- |
| `src/templates/styles/email.css` | Single source of truth for styles and theme tokens (colours and corner radii) |
| `src/templates/styles/themes/` | Themes: a `:root` block overriding some tokens, nothing else |
| `src/templates/layout.html.twig` | Shared shell: preheader, header, body, footer, media queries and dark mode |
| `src/templates/layout.txt.twig` | Shared plain-text shell: body, footer and help link; the host supplies the subject header |
| `src/templates/components/` | Presentational partials (button, actions, details, notice, code, digest list, spacer) |
| `src/templates/messages/` | One `.html.twig` + `.txt.twig` pair per example message; text versions extend the shared shell |
| `src/templates/export.html.twig` | Applies `inline_css` to the **whole document** |
| `src/theme.php` | Resolves theme tokens (`var(--…)`) before inlining |
| `src/fixtures/demo.json` | Fictitious data for the examples, including brand strings; a message may override part of `brand`, and `themes.<name>` overrides demo data per theme |
| `src/assets/` | Fictitious demo images, copied to `dist/assets/` by the build |
| `bin/build.php` | Renders `dist/`; `--check` compares without writing |
| `tests/smoke.php` | Structural checks on the rendered output |
| `tests/css.php` | Stylesheet lint: hex, ids, duplicates, `!important`, remote assets |
| `tests/render.php` | Render-level checks: inliner fidelity, media queries, MSO width, Outlook-safe spacing, size, `bgcolor`/CSS agreement |

## What is inside

| Email | Components exercised |
| --- | --- |
| Appointment confirmation | Date as title, details, action, reschedule hint |
| Invitation | Logo header, details, two actions, expiry |
| Daily summary | Date as title, count in the lead, repeated list, action |
| Security code | Code, expiry, request context, warning with recovery link |
| Password reset | Single action, fallback URL, validity, notice |

Each sample has a meaningful plain-text version with the same links as the HTML. `actions` places one or two buttons side by side; below 620px they stack full-width so translated labels don't get cramped. The CSS deliberately keeps to email-friendly structures; emails contain **no JavaScript or external fonts, and never depend on images**: with images blocked, every message stays complete and readable. The preheader is followed by invisible filler characters so inbox previews don't pull in body text.

## Change the design

### Colours and shapes (theme tokens)

All colours and corner radii (`--radius-card`, `--radius-box`, `--radius-control`, `--radius-mark`) are defined once, as custom properties in the `:root` block at the top of `src/templates/styles/email.css`:

```css
:root {
  --accent: #14766a;
  --ink: #1c3549;
  --muted: #5b6e7b;
  /* … */
}
```

Email clients and the CSS inliner don't support `var()`, so `src/theme.php` resolves every `var(--token)` at build time. The same tokens are exposed to Twig as `theme.*` (dashes become underscores, e.g. `--accent-ink` → `theme.accent_ink`) for HTML attributes such as `bgcolor` that must repeat a colour for Outlook.

The build fails on an unknown token, or on a literal hex colour outside `:root`. Add a token instead of hard-coding a colour. `composer test` checks WCAG AA contrast (4.5:1) for every text/background token pair the layout uses, light and dark, in the default palette and every theme (`tests/contrast.php`); add a pair there when a component combines two tokens in a new way.

### Themes

A theme is a file in `src/templates/styles/themes/` holding only a `:root` block that redefines some existing tokens: `sharp.css` squares every corner with a monochrome palette, `soft.css` uses large radii and a warm clay palette. The build renders every message in every theme; adding a theme needs no template change. `actual_emails_theme($css, $themeCss)` rejects unknown tokens and any rule other than `:root`, so a typo fails instead of silently keeping the default. A host usually copies one theme's values into its own `:root` rather than shipping several. Demo data can differ per theme through `themes.<name>` in the fixture, merged last: the teal demo logo belongs to the default theme, so `sharp` and `soft` fall back to the text mark. A real brand ships a symbol that matches its palette.

Word-based Outlook squares every corner whatever the radius, so the rounded themes degrade to the sharp shape there.

### Dark mode

The `--dark-*` tokens feed the `prefers-color-scheme: dark` block in the layout. Clients can still force their own inversion, so treat this as a best effort, not a guarantee.

### Everything else

- Typography and spacing: `src/templates/styles/email.css`.
- Shared shell: `src/templates/layout.html.twig`; shared text shell: `src/templates/layout.txt.twig`.
- Brand symbol: by default a letter in a coloured square (`brand.mark`). An optional `brand.logo` (`url`, `width`, `height`) replaces only that square with an image; `brand.name` stays as text beside it, so the image is decorative (`alt=""`). It is designed for a square symbol, not for a logotype that already contains the name. Provide a PNG at twice the displayed size, keep `width`/`height` (rendered as HTML attributes, which Outlook needs), and check that it stays visible on light and dark backgrounds: a transparent PNG is fine if its shape keeps contrast after inversion; otherwise give it its own fill, outline or halo. Never use SVG as the only format. When sending, use an absolute, stable HTTPS URL shared by all recipients (no recipient identifier, which would turn the image into a tracker). The demo's relative `assets/logo.png` exists only to keep the gallery offline.
- Layout chrome strings (logo mark, help label, fallback-link label, website label, optional `brand.footer_note`) are brand parameters in `src/fixtures/demo.json`, not markup, so they can be translated. The demo omits the footer note.
- There is no eyebrow label above the title: the title states the fact (a date, a code, an action) and the lead adds who and what, without repeating the title.
- `details`, `digest_list` and `code` are ruled rows inside the card, not boxes inside a box. `notice` accepts an optional `link` (`{label, url}`) placed after its message, for a recovery action such as changing a password. `subcopy` requires an explicit translated `label` and `url`; callers that relied on its former English default must now supply the label.
- Example messages: `src/templates/messages/`.
- Fixture data: `src/fixtures/demo.json`, then `composer build`.
- `actions` places one or two buttons side by side and stacks them full-width below 620px; keep labels short and check translated lengths at 320px anyway.
- Email links are plain GET requests, and security scanners and link previewers open them automatically. A link must never perform a state change on its own: a secondary action such as the invitation's "Decline" leads to a confirmation page in the host application, which performs the change on an explicit POST. The same applies to unsubscribe, cancel or confirm links. This is a contract for the host; the kit only renders the link.
- `digest_list` requires a `title` per row; `time` and `subtitle` are optional. Lists without times omit the time column; mixed lists keep that column aligned. The text version also accepts untimed rows.
- Plain-text bodies omit the subject and put each URL on its own line below its label.
- Demo copy conventions worth keeping when adapting: one name per concept ("sign-in code" in subject, title and text), 24-hour times with their time zone, and the same request context (time and device) in the same place for every security message, just before the "not you?" notice. That notice stays neutral ("Didn’t request this code?"): a code may be sent without a password check, as in passwordless sign-in, so it must not claim the password is compromised.

## Integration direction

See [docs/architecture.md](docs/architecture.md) for the proposed boundaries and an integration checklist for a host application. In particular, do **not** replace the host application's mail delivery pipeline with this demo renderer. The integrating app needs the same token resolution: call `actual_emails_theme()` from `src/theme.php` (framework-neutral) and pass its CSS to `inline_css`.

## Quality status

**Not production-certified yet.** The gallery is a browser preview, not a client test matrix. Real sends (November 2026, via SMTP and testi@ screenshots) render correctly in Gmail web, Gmail Android (light and dark), Outlook web/new, iPhone 12 mini, Roundcube and AOL (preheader). Outlook 2019 (Word engine) renders the layout, spacing and buttons correctly, with one known artefact: see [docs/next-steps.md](docs/next-steps.md). Still to validate: the other messages in Outlook 2019/365, Apple Mail desktop, long text, forced dark mode and translated message lengths. The provided dark styles are best effort and may be overridden by client-driven inversion. This repository includes an output smoke test, not actual client screenshot testing.

## Reference ideas / attribution

The original HTML/CSS patterns in this repository are inspired by the public techniques documented by [Cerberus](https://www.cerberusemail.com/templates), [Foundation for Emails](https://get.foundation/emails/docs/css-guide.html), [Postmark templates](https://github.com/ActiveCampaign/postmark-templates) and [Twig's `inline_css` extension](https://twig.symfony.com/doc/3.x/filters/inline_css.html). No upstream template is included verbatim. Verify licenses if copying upstream code later.
