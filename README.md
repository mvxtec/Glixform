# Glixform

A drag-and-drop form builder for WordPress. Build forms, collect entries, get email notifications.

**Status:** Phase 1 (MVP), version 0.1.0.

## What's in Phase 1

| Area | Features |
|---|---|
| Builder | Add, edit, reorder (drag and drop or arrow buttons), duplicate and delete fields; per-form settings; preview; unsaved-changes warning |
| Fields | Single Line Text, Paragraph Text, Email, Number (min/max/step), Dropdown, Multiple Choice, Checkboxes, Hidden |
| Embedding | `[glixform id="123"]` shortcode and a **Glixform** block for the block editor |
| Submissions | AJAX submit with client-side validation; works without JavaScript too (server-side validation, Post/Redirect/Get) |
| Spam | Honeypot field + signed time token (rejects bots that post instantly or without loading the form) |
| Notifications | HTML email with smart tags, Reply-To from a field, custom From name |
| Confirmation | Message (with smart tags), redirect to a page, or redirect to a URL |
| Entries | List with search, read/unread, bulk actions, single-entry view, CSV export |
| Settings | Store/skip visitor IP, minimum submit time, delete data on uninstall |

### Smart tags

`{all_fields}`, `{field_id="N"}`, `{form_name}`, `{form_id}`, `{entry_id}`, `{date}`, `{page_url}`, `{site_name}`, `{site_url}`, `{admin_email}`

## Requirements

WordPress 6.0+ and PHP 7.4+.

## Installation (development)

```bash
git clone https://github.com/mvxtec/Glixform.git wp-content/plugins/glixform
cd wp-content/plugins/glixform
composer install      # optional: dev tools; the plugin has a built-in autoloader fallback
```

Activate **Glixform** under *Plugins*, then go to **Glixform → Add New**.

## Development

```bash
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
assets/                      Plain JS/CSS (no build step yet)
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

- **Phase 2:** React builder with live preview, more fields (phone, date, address, URL, file upload,
  page break), conditional logic, multiple notifications, reCAPTCHA/Turnstile, form templates,
  import/export, GDPR tools, REST API.
- **Phase 3:** Payments (Stripe), integrations (Mailchimp, webhooks, Zapier), calculations,
  save & resume, user registration.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
