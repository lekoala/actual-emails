# Toward 0.2

0.1.0 is tagged. 0.2 stabilises what exists: validation in real clients, not new components, options or lint rules. The kit provides compositions and conventions; the host keeps business content, date and time formatting, and state-changing endpoints.

## Done for 0.2

- Contrast guard: `tests/contrast.php` checks WCAG AA (4.5:1) for every text/background token pair the layout uses, light and dark, in all three palettes.
- Neutral security wording: "Didn’t request this code? Review your account security." no longer assumes a password was checked (passwordless sign-in).
- Documented contract: email links never change state on a GET; "Decline" leads to a confirmation page (README, architecture).

## Blocking 0.2 (validation pass)

1. **Outlook 2019 thin lines (open)**: in the appointment message, a 1px full-width line appears under the button and under the notice; the digest does not show it. Likely cause (unconfirmed): Word rounds fractional heights (e.g. `12px × 1.7`) under Windows display scaling and leaves a 1px gap where the page colour shows through, because the card's white background is on the `<table>`, not its cells. Fix to try: `bgcolor` + `background-color: var(--surface)` on the `.email-header`, `.email-body` and `.email-card-foot` cells, also added to the dark-mode block. The integration pilot reports that these backgrounds cover the card's rounded corners unless the header and foot also get matching top and bottom radii (currently 18px). No Outlook 2019 render validates this workaround yet. Before keeping it, render the appointment in Outlook 2019 and check the corners in Gmail and Apple Mail as well.
2. **Real-client pass on the riskiest compositions**: already checked in Gmail web/Android, Outlook web/new, Outlook 2019 (appointment, digest), iPhone 12 mini, Roundcube and AOL. Still to capture, in Outlook 2019/365, Gmail apps and Apple Mail desktop:
   - the invitation with two actions, at 320px and with the longest supported translation of both labels;
   - a long organisation name in the header, with and without the optional `brand.logo`;
   - the logo with images blocked and in forced dark mode (Gmail apps, Outlook); Outlook 2019 sizing;
   - reset and security in Outlook 2019/365;
   - the secondary button and notices in dark mode;
   - iOS date/address auto-detection.
3. **Dark mode**: the `prefers-color-scheme` block is a best effort. Record what each client above actually does with it; do not promise a theme beyond what is measured. Forced dark mode is not consistently controllable.

Record each result (client, version, message, theme, pass/fail, screenshot) so 0.2 can state exactly what was validated.

## Before integrating (host side, not kit work)

- **Deliverability**: a Gmail seed account flagged a test send as likely spam. Confirm SPF, DKIM and DMARC pass and align for the sending domain ("Show original" in Gmail).
- **Integration pilot**: integrate only the appointment example into a host application, retaining its production translations, URLs and mail-composer contract. Run the host project's template checks and a real delivery through a local mail catcher such as Mailpit.
- **Stress content** in the host's own fixtures: long person and product names, 200% text zoom, unusually long links and email addresses, disabled images and blocked remote resources.

## Deferred until a concrete need

- **Component API**: if a second consumer emerges, consider framework-neutral HTML snippets and an optional Twig package. No Symfony bundle in advance.
- **Brand strategy**: per-tenant vs platform identity, and whether a full logotype (name inside the image) deserves its own header variant. Never depend on SVG rendering in Outlook.
- **Light-only mode**: see architecture, "Light-only hosts".

No delivery queue, database, i18n catalog, live template editor, SMTP transport, date formatting or other application infrastructure should be introduced into this kit.
