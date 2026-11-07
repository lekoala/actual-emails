# Actual Emails · 0.1

A small transactional email design kit, inspired by the philosophy of Actual CSS: content-first, clean defaults, just enough components and no frontend build chain. The output HTML is **standalone**; the included Twig renderer is only the authoring workflow.

## Open the preview

1. Open **`preview/index.html`** in a browser. Select one of the four example messages and switch between the desktop and mobile viewports.
2. Open **`dist/*.html`** to see finished emails, or **`dist/*.txt`** for their plain-text counterparts.

All demonstration data is fictitious and URLs point to `example.org`; they are intentionally non-functional. The preview loads no external assets. It is a development tool only: browser rendering is not validation in Gmail, Apple Mail or Outlook.

## Rebuild (PHP 8.2+ and Composer)

```sh
composer install
composer build   # render dist/*.html and dist/*.txt
composer test    # fail if dist/ is stale, then run the output smoke test
```

No Symfony app, Node process or `package.json` is needed. `dist/` is generated and committed only so the gallery works **without installing anything**: never edit it by hand, rebuild it instead. `composer test` (via `php bin/build.php --check`) fails when `dist/` no longer matches the sources, then runs the output smoke test: structure, inlined styles, class/stylesheet drift and HTML/plain-text link parity.

## Project layout

| Path | Role |
| --- | --- |
| `src/templates/styles/email.css` | Single source of truth for styles and theme tokens |
| `src/templates/layout.html.twig` | Shared shell: preheader, header, body, footer, media queries and dark mode |
| `src/templates/layout.txt.twig` | Shared plain-text shell: subject, body, footer and help link |
| `src/templates/components/` | Presentational partials (button, actions, details, notice, code, digest list, spacer) |
| `src/templates/messages/` | One `.html.twig` + `.txt.twig` pair per example message; text versions extend the shared shell |
| `src/templates/export.html.twig` | Applies `inline_css` to the **whole document** |
| `src/theme.php` | Resolves theme tokens (`var(--…)`) before inlining |
| `src/fixtures/demo.json` | Fictitious data for the examples, including brand strings |
| `bin/build.php` | Renders `dist/`; `--check` compares without writing |
| `tests/smoke.php` | Structural checks on the rendered output |

## What is inside

| Email | Components exercised |
| --- | --- |
| Appointment confirmation | Details, action, link, notice |
| Invitation | Details, two actions, expiry |
| Daily summary | KPI, repeated list, action |
| Security code | OTP, expiry, warning |
| Password reset | Single action, fallback URL, warning |

Each sample has a meaningful plain-text version with the same links as the HTML. `actions` places one or two buttons side by side; below 620px they stack full-width so translated labels don't get cramped. The CSS deliberately keeps to email-friendly structures; emails contain **no JavaScript, external fonts or remote images**. The preheader is followed by invisible filler characters so inbox previews don't pull in body text.

## Change the design

### Colours (theme tokens)

All colours are defined once, as custom properties in the `:root` block at the top of `src/templates/styles/email.css`:

```css
:root {
  --accent: #14766a;
  --ink: #1c3549;
  --muted: #5b6e7b;
  /* … */
}
```

Email clients and the CSS inliner don't support `var()`, so `src/theme.php` resolves every `var(--token)` at build time. The same tokens are exposed to Twig as `theme.*` (dashes become underscores, e.g. `--accent-ink` → `theme.accent_ink`) for HTML attributes such as `bgcolor` that must repeat a colour for Outlook.

The build fails on an unknown token, or on a literal hex colour outside `:root`. Add a token instead of hard-coding a colour. Check text contrast when changing tokens: `--muted` is used for small text on both `--surface` and `--page`.

The `--dark-*` tokens feed the `prefers-color-scheme: dark` block in the layout. Clients can still force their own inversion, so treat this as a best effort, not a guarantee.

### Everything else

- Typography and spacing: `src/templates/styles/email.css`.
- Shared shell: `src/templates/layout.html.twig`; shared text shell: `src/templates/layout.txt.twig`.
- Layout chrome strings (logo mark, help label, footer note) are brand parameters in `src/fixtures/demo.json`, not markup, so they can be translated.
- Example messages: `src/templates/messages/`.
- Fixture data: `src/fixtures/demo.json`, then `composer build`.
- `actions` places one or two buttons side by side and stacks them full-width below 620px; keep labels short and check translated lengths at 320px anyway.

## Integration direction

See [docs/architecture.md](docs/architecture.md) for the proposed boundaries and an integration checklist for a host application. In particular, do **not** replace the host application's mail delivery pipeline with this demo renderer. The integrating app needs the same token resolution: call `actual_emails_theme()` from `src/theme.php` (framework-neutral) and pass its CSS to `inline_css`.

## Quality status

**Not production-certified yet.** The gallery is a browser preview, not a client test matrix. Real sends have been checked in Gmail web, Gmail Android and Outlook web/new (November 2026). Still to validate: Outlook for Windows (Word engine), Apple Mail/iOS, long text, image blocking (no images currently), forced dark mode and translated message lengths. The provided dark styles are best effort and may be overridden by client-driven inversion. This repository includes an output smoke test, not actual client screenshot testing.

## Reference ideas / attribution

The original HTML/CSS patterns in this repository are inspired by the public techniques documented by [Cerberus](https://www.cerberusemail.com/templates), [Foundation for Emails](https://get.foundation/emails/docs/css-guide.html), [Postmark templates](https://github.com/ActiveCampaign/postmark-templates) and [Twig's `inline_css` extension](https://twig.symfony.com/doc/3.x/filters/inline_css.html). No upstream template is included verbatim. Verify licenses if copying upstream code later.
