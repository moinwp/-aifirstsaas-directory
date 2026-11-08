# AI First SaaS Directory

Data and presentation layer for [aifirstsaas.com](https://aifirstsaas.com) — an independent directory cataloging AI features across SaaS tools.

## Status

**Version 0.3.0** — Session 3 complete. The plugin:

- Activates cleanly and creates 6 custom tables via `dbDelta()`
- Registers the `aifirst_tool` CPT (public slug: `tools`), `aifirst_category`, and `aifirst_feature`
- Ships a canonical record contract (`AIFirst_Record`) and a single write path (`AIFirst_Intake::save_record`)
- Exposes `POST /wp-json/aifirst/v1/tool` (idempotent upsert, requires `manage_options`)
- Adds a **Quick Add** admin page with two tabs:
  - **Paste & Parse** — paste text, parse with AI, review structured draft, save
  - **Bulk CSV import** — upload many tools at once, no AI required
- Adds a **Settings** page for the AI provider (Anthropic / OpenAI), model, and API key, plus a Test Connection button

Session 3 adds 7 new tables (`tool_features`, `maturity_breakdown`, `industries`, `sentiment`, `quotes`, `category_meta`, `feature_meta`), changes `company_size_{smb,mid,ent}` from booleans to percentages, adds `company_group_id` to `tool_meta`, and adds a `workaround` column to `cannot_do`. Upgrade from 0.2.0 → 0.3.0 is automatic on the next admin page load — no reactivation.

Not yet built: frontend templates, CSS/JS for the public site, additional REST endpoints, child theme, MCP adapter.

## Where to find things

| Thing | Path |
| --- | --- |
| Quick Add | `Tools → Quick Add` |
| Settings | `Tools → Settings` |
| REST endpoint | `POST /wp-json/aifirst/v1/tool` |
| API key override | `define( 'AIFIRST_AI_API_KEY', '…' );` in `wp-config.php` |

See `NOTES.md` for the full record schema, the REST payload example with curl, and the CSV header layout.

## Requirements

- WordPress 6.4+
- PHP 8.1+

## Install

Drop the folder into `wp-content/plugins/` and activate via **Plugins** in the WP admin.

## Uninstall

Deleting the plugin from the WP admin runs `uninstall.php`, which drops all 6 custom tables and removes plugin options. Deactivating does **not** remove data.

## Conventions

| Thing | Value |
| --- | --- |
| Text domain | `aifirstsaas` |
| Function prefix | `aifirst_` |
| Class prefix | `AIFirst_` |
| Constant prefix | `AIFIRST_` |
| DB table prefix | `{$wpdb->prefix}aifirst_` |
| CPT slug | `aifirst_tool` (rewrite: `tools`) |
| Taxonomy slugs | `aifirst_category`, `aifirst_feature` |
| REST namespace (custom) | `aifirst/v1` |
