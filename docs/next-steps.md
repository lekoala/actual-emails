# After 0.2

0.2.0 is tagged. Like 0.2, the next step stabilises what exists: validation in real clients, not new components, options or lint rules. The kit provides compositions and conventions; the host keeps business content, date and time formatting, and state-changing endpoints.

## What 0.2 contains

- Contrast guard: `tests/contrast.php` checks WCAG AA (4.5:1) for every text/background token pair the layout uses, light and dark, in all three palettes.
- Neutral security wording: "Didn’t request this code? Review your account security." no longer assumes a password was checked (passwordless sign-in).
- Documented contract: email links never change state on a GET; "Decline" leads to a confirmation page (README, architecture).
- Outlook 2019 thin lines fixed. An A/B test showed that `bgcolor` on the card cells changes nothing, so it was dropped. The seam came from line heights: Outlook did not apply the unitless values (16px text set 21px apart instead of 26.4px) and left a 1px gap showing the page colour. Every line height is now whole pixels with `mso-line-height-rule: exactly`, and the lint rejects unitless line heights. The `&nbsp;` gap cell between two actions, which showed the page colour too, is now padding on the second cell. Checked in Outlook 2019 at 100%: security, appointment, invitation; Outlook now also gets the intended spacing.
- Detail labels no longer break mid-word on phones ("Workspac-e"): they use `word-break: normal`, values keep `break-word` for long URLs. Checked at 320px and in Gmail Android (dark).
- Stricter radius test: every value of a `border-radius` shorthand must come from a token.

## Still to validate

1. **Outlook 2019 at 125% and 150% display scaling**, and digest and reset after the line-height change. If a seam returns, look at cell padding next, not line heights.
2. **Real-client pass on the riskiest compositions**: already checked in Gmail web/Android, Outlook web/new, Outlook 2019 (appointment, digest, security, invitation), iPhone 12 mini, Roundcube and AOL. Still to capture, in Outlook 2019/365, Gmail apps and Apple Mail desktop:
   - the invitation with two actions, at 320px and with the longest supported translation of both labels;
   - a long organisation name in the header, with and without the optional `brand.logo`;
   - the logo with images blocked and in forced dark mode (Gmail apps, Outlook); Outlook 2019 sizing;
   - reset in Outlook 2019, and all messages in Outlook 365;
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
