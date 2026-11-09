# Toward 0.2

0.1.0 is tagged. 0.2 stabilises what exists: validation in real clients, not new components, options or lint rules. The kit provides compositions and conventions; the host keeps business content, date and time formatting, and state-changing endpoints.

## Done for 0.2

- Contrast guard: `tests/contrast.php` checks WCAG AA (4.5:1) for every text/background token pair the layout uses, light and dark, in all three palettes.
- Neutral security wording: "Didn’t request this code? Review your account security." no longer assumes a password was checked (passwordless sign-in).
- Documented contract: email links never change state on a GET; "Decline" leads to a confirmation page (README, architecture).

## Blocking 0.2 (validation pass)

1. **Outlook 2019 thin lines (fixed at 100%, scaling to check)**: first seen on 7 November in the former appointment (under the button and under the notice); the digest did not show it. A/B test on 9 November, security message, Outlook 2019 at 100% scaling: the version with `bgcolor` on the header, body and foot cells is pixel-identical to the version without it, so that workaround was reverted. Both show one 1px full-width line in the page colour (`--page`) inside the card foot, at the bottom of the footer text's line box and above the cell's bottom padding: the body background shows through a seam that cell shading does not cover. Same render: Outlook does not apply the unitless line heights (16px body text is set 21px apart instead of 26.4px; the 13px foot text gets a 17px line box instead of 21.45px). Applied: every line height in whole pixels (e.g. 16px → 26px, 14px → 22px, 13px → 21px) with `mso-line-height-rule: exactly`; the stylesheet lint now rejects unitless line heights. Checked 9 November in Outlook 2019 at 100%: security, appointment and invitation have no seam and the intended spacing (26px between body lines). The invitation showed the page colour in the `&nbsp;` gap cell between its two buttons; the gap is now padding on the second action cell (to recheck). Still to check: 125% and 150% scaling, digest and reset.
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
