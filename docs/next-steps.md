# What to validate next

1. **Outlook 2019 thin lines (open)**: in the appointment message, a 1px full-width line appears under the button and under the notice; the digest does not show it. Likely cause (unconfirmed): Word rounds fractional heights (e.g. `12px × 1.7`) under Windows display scaling and leaves a 1px gap where the page colour shows through, because the card's white background is on the `<table>`, not its cells. Fix to try: `bgcolor` + `background-color: var(--surface)` on the `.email-header`, `.email-body` and `.email-card-foot` cells, also added to the dark-mode block. Validate with a real Outlook render before keeping it.
2. **Real email clients**: already checked in Gmail web/Android, Outlook web/new, Outlook 2019 (appointment, digest), iPhone 12 mini, Roundcube and AOL. Still to capture: invitation, reset and security in Outlook 2019/365; Apple Mail desktop; the secondary button in dark mode; iOS date/address auto-detection.
3. **Deliverability check**: a Gmail seed account flagged a test send as likely spam. Confirm SPF, DKIM and DMARC pass and align for the sending domain ("Show original" in Gmail) before integrating.
4. **Integration pilot**: integrate only the appointment example into a host application, retaining its production translations, URLs and mail-composer contract. Run the host project's template checks and a real delivery through a local mail catcher such as Mailpit.
5. **Stress fixtures**: long person, organisation and product names; 200% text zoom; unusually long links and email addresses; phone screen 320px; disabled images and blocked remote resources; two action labels in every supported locale, including longer translations.
6. **Component API**: if a second consumer emerges, consider publishing framework-neutral HTML snippets and an optional Twig package. Do not create a Symfony bundle in advance.
7. **Brand strategy**: optional text-only mark, external hosted PNG logo when needed, per-tenant identity vs platform identity. Never depend on SVG rendering in Outlook.
8. **Dark mode**: the provided `prefers-color-scheme` block is a best effort. Measure how each target client renders it before promising a theme; forced dark mode is not consistently controllable.
9. **Accessibility/security**: semantic content, good contrast, visible fallback URLs for security-critical actions, email-minimal personal data.

No delivery queue, database, i18n catalog, live template editor, SMTP transport or other application infrastructure should be introduced into this kit.
