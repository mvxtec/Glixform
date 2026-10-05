# Glixform

A drag-and-drop form builder for WordPress. Build forms, collect entries, get email notifications.

**Status:** Phase 2, version 0.2.0.

## Features

| Area | Features |
|---|---|
| Builder | React app: template picker (9 templates), drag-and-drop fields (also from the palette), live preview with desktop/mobile sizes (click a field in the preview to edit it), undo/redo, Ctrl+S, searchable field palette |
| Fields (19) | Text, Paragraph, Email, Number, Dropdown, Multiple Choice, Checkboxes, Hidden, Name, Phone, Website/URL, Date/Time, Address, File Upload, Rating, GDPR Consent, Page Break, Section Divider, HTML |
| Logic | Show/hide fields, send notifications and pick confirmations based on answers (all/any rules, 10 operators). Evaluated identically in the browser and on the server |
| Multi-page | Page breaks with a progress bar or numbered steps; per-page validation |
| Embedding | `[glixform id="123"]` shortcode and a **Glixform** block |
| Submissions | AJAX with client-side validation; full no-JavaScript fallback |
| Spam | Honeypot, signed time token, Cloudflare Turnstile, hCaptcha, reCAPTCHA v2/v3, Akismet with a spam folder |
| Notifications | Any number per form, each with conditions, smart tags, Reply-To and From name; clean HTML email |
| Confirmations | Any number per form: message, page or URL, chosen by conditions |
| Entries | Search, read/unread/spam views, bulk actions, single view with file downloads, CSV export |
| Files | Type/size/count limits, real type checks, random names, protected folder, admin-only downloads |
| Privacy | WordPress personal data export/erase, automatic deletion after N days, optional IP storage |
| Tools | Import/export forms as JSON |
| API | REST API under `/wp-json/glixform/v1` (forms, entries, templates, preview) |

### Smart tags

`{all_fields}`, `{field_id="N"}`, `{form_name}`, `{form_id}`, `{entry_id}`, `{date}`, `{page_url}`, `{page_title}`,
`{site_name}`, `{site_url}`, `{admin_email}`, `{user_id}`, `{user_email}`, `{user_display_name}`, `{user_first_name}`,
`{user_last_name}`, `{user_ip}`, `{query_var key="name"}`, `{unique_id}`. Default values accept smart tags too.

## Requirements

WordPress 6.0+ and PHP 7.4+.

## Installation (development)

```bash
git clone https://github.com/mvxtec/Glixform.git wp-content/plugins/glixform
cd wp-content/plugins/glixform
composer install      # optional: PHP dev tools; the plugin has a built-in autoloader fallback
npm install && npm run build   # only needed after changing assets/src (build/ is committed)
```

Activate **Glixform** under *Plugins*, then go to **Glixform → Add New**.

## Development

```bash
npm run start         # rebuild the builder on every change
npm run lint:js       # JavaScript lint
composer test         # PHPUnit unit tests
composer lint         # WordPress Coding Standards + PHP 7.4 compatibility
composer lint:fix     # auto-fix what phpcbf can
```

If Composer plugins are disabled in your environment, register the coding standards once with
`vendor/bin/phpcs --config-set installed_paths …` (see `vendor/` for the paths).

## Architecture

```
glixform.php                 Bootstrap: constants, PSR-4 autoload, activation hook
uninstall.php                Optional data removal
src/
  Plugin.php                 Service container; wires everything together
  Install.php                Creates/upgrades the custom tables (dbDelta, schema version)
  Fields/                    AbstractField + one class per field type, FieldRegistry
  Forms/                     "glixform_form" post type; FormRepository (JSON in post_content)
  Database/EntryRepository   {prefix}glixform_entries + {prefix}glixform_entry_fields
  Frontend/                  Renderer, Shortcode, Block, Preview, Assets
  Process/                   Submission pipeline, AJAX/POST controller, AntiSpam
  Notifications/             SmartTags, Mailer
  Admin/                     Menus, Forms list, Builder, Entries, CSV export, Settings
  Forms/ConditionalLogic     Rules engine (mirrored in assets/js/frontend.js)
  Forms/Templates            Starter templates
  Rest/                      REST API
  Support/                   Uploads, Privacy (export/erase, retention)
assets/js, assets/css        Front end (plain JS/CSS, no build)
assets/src                   Builder source (React, built with @wordpress/scripts into build/)
tests/Unit/                  PHPUnit tests
```

**Data model**

- A form is a `glixform_form` post. Its definition is JSON in `post_content`:
  `{ "fields": [...], "settings": {...}, "next_field_id": N }`.
- An entry is a row in `glixform_entries`, with a JSON snapshot of every field (label, type, value),
  so old entries stay readable after a form changes. `glixform_entry_fields` holds one row per value
  for searching.

**Submission pipeline** (`src/Process/Submission.php`):
load form → honeypot → token → sanitize & validate each field → save entry → send notification → confirmation.

### Extending

| Hook | Type | Use |
|---|---|---|
| `glixform_field_types` | filter | Register a field type class (extend `Glixform\Fields\AbstractField`) |
| `glixform_validate_field` | filter | Add custom validation for a field |
| `glixform_validation_errors` | filter | Cross-field validation |
| `glixform_entry_saved` | action | Entry stored |
| `glixform_process_complete` | action | Submission finished — integrations hook here |
| `glixform_notification_email` | filter | Change the email before it is sent |
| `glixform_smart_tags` | filter | Add smart tags |
| `glixform_form_html` | filter | Change the rendered form |
| `glixform_capability` | filter | Capability needed to manage forms (default `manage_options`) |
| `glixform_client_ip` | filter | Trust a proxy header for the visitor IP |

## Security notes

- Every admin action checks the capability and a nonce.
- Public forms don't use nonces (page caches would break them); they use an HMAC-signed timestamp instead.
- Builder input is re-sanitized on the server; unknown field types and options are dropped.
- Choice fields accept only configured choices.
- Notification addresses are validated and stripped of line breaks (no header injection); the From
  address stays the site default.
- CSV cells that start with `= + - @` are prefixed with `'` (spreadsheet formula injection).

## Roadmap

- **Phase 3:** Payments (Stripe), integrations (Mailchimp, webhooks, Zapier), calculations,
  save & resume, user registration, repeater field, surveys and reports.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
