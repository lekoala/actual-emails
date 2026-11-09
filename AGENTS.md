# Actual Emails — agent contract

- This is a design system for **transactional email**, not a sending service.
- No JavaScript, tracking pixels, external fonts or embedded forms in delivered email. No functional dependency on remote images: an email stays complete and readable when images are blocked. The only image is the optional `brand.logo` symbol, beside the brand name kept as text; never per-recipient image URLs.
- Keep the email markup one-column, table-based, and degradable without CSS. Treat Outlook Word as a separate compatibility target.
- All samples have HTML and meaningful plain-text counterparts.
- Source-of-truth styles: `src/templates/styles/email.css`; do not hand-edit `dist`.
- Export using `inline_css` on the **whole document**; `dist` is generated and committed only to ease evaluation.
- Twig autoescape (by file name: `*.html.twig` escaped, `*.txt.twig` plain text) and strict variables must stay enabled. Never mark user-generated text `raw`.
- Samples must use fictitious data and fictitious example.org URLs. Do not put real personal data in demos.
- Integrating the kit must not change the host application's delivery intents, routing, locales, translations, or mail transport.
- No new templates or abstractions unless a concrete message needs them.
- Preview is a development tool. Browser appearance is not validation in Gmail, Apple Mail, or Outlook.
