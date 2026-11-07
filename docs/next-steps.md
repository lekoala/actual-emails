# What to validate next

1. **Real email clients**: capture Gmail web and mobile, Apple Mail, Outlook web/new, and Outlook for Windows Word-based engines. In particular, verify conditional tables, primary CTA hit area, stacked two-action layout at 320px, iOS date/address auto-detection, dark-mode rendering and fallback fonts.
2. **Integration pilot**: integrate only the appointment example into a host application, retaining its production translations, URLs and mail-composer contract. Run the host project's template checks and a real delivery through a local mail catcher such as Mailpit.
3. **Stress fixtures**: long person, organisation and product names; 200% text zoom; unusually long links and email addresses; phone screen 320px; disabled images and blocked remote resources; two action labels in every supported locale, including longer translations.
4. **Component API**: if a second consumer emerges, consider publishing framework-neutral HTML snippets and an optional Twig package. Do not create a Symfony bundle in advance.
5. **Brand strategy**: optional text-only mark, external hosted PNG logo when needed, per-tenant identity vs platform identity. Never depend on SVG rendering in Outlook.
6. **Dark mode**: the provided `prefers-color-scheme` block is a best effort. Measure how each target client renders it before promising a theme; forced dark mode is not consistently controllable.
7. **Accessibility/security**: semantic content, good contrast, visible fallback URLs for security-critical actions, email-minimal personal data.

No delivery queue, database, i18n catalog, live template editor, SMTP transport or other application infrastructure should be introduced into this prototype.
