# NOTES — AI First SaaS Directory

Running log of decisions and open items. Dated entries at the top.

## Session 2 — Intake + AI parse + CSV + REST (2026-11-07)

Version bumped to **0.2.0**.

### What's new

- `AIFirst_Record` — canonical record shape, validator and sanitizer. The single source of truth for the record contract. Every other piece (admin UI, CSV, REST, AI client) produces or consumes this exact shape.
- `AIFirst_Intake::save_record()` — the single write path. Idempotent upsert keyed on `tool_id` (stored as post meta `_aifirst_tool_id` on the CPT, and as a column on every custom table). Writes the CPT post, assigns taxonomies, upserts `tool_meta`, then delete-and-inserts rows for `plans`, `faq`, `radar`, `cannot_do`, `relationships`. Called from both the admin UI and the REST endpoint.
- `AIFirst_AI_Client` — Anthropic (`/v1/messages`) and OpenAI (`/v1/chat/completions`, JSON mode) adapters. One method `parse_tool_text( $raw, $site )` returns a canonical record array or `WP_Error`. Only the pasted text and the schema instruction are sent to the provider.
- `AIFirst_REST` — namespace `aifirst/v1`. One endpoint today: `POST /aifirst/v1/tool`. Permission callback requires `manage_options`, so it works with both nonce-authenticated admin calls and Application Password calls.
- `AIFirst_Admin` + `AIFirst_Quick_Add` + `AIFirst_Settings` + `AIFirst_Ajax` — two admin pages under the Tools menu: Quick Add (paste & parse + bulk CSV) and Settings (provider, model, API key, test connection).
- `AIFirst_CSV_Importer` — deterministic bulk import. No AI involved.
- `assets/css/admin-quick-add.css` + `assets/js/admin-quick-add.js` — enqueued only on the two admin screens. jQuery-based (bundled with WP); no build step.

### REST endpoint

**`POST /wp-json/aifirst/v1/tool`**

Requires `manage_options`. Send either a WordPress REST nonce (`X-WP-Nonce` header) or Basic auth with an Application Password.

Returns:
- `201 Created` on first save of a `tool_id`
- `200 OK` on update (same `tool_id` already existed)
- `400 Bad Request` with `errors: []` on validation failure
- `500` on write failure

#### Minimal example payload

```json
{
  "tool": {
    "tool_id": "jasper",
    "title": "Jasper",
    "content": "<p>AI writing platform.</p>",
    "excerpt": "AI writing for marketing teams.",
    "categories": ["Content Marketing", "Writing"],
    "ai_features": ["AI Writing", "Templates"],
    "status": "draft"
  },
  "meta": {
    "company_name": "Jasper AI, Inc.",
    "website_url": "https://www.jasper.ai",
    "pricing_url": "https://www.jasper.ai/pricing",
    "pricing_model": "subscription",
    "has_free_plan": 0,
    "starting_price_usd": 39.00,
    "ai_type": "native",
    "ai_maturity_score": 9,
    "last_verified": "2026-11-07",
    "meta_title": "Jasper — AI writing platform",
    "meta_description": "Marketing-focused AI writing with templates and brand voice.",
    "h1": "Jasper"
  },
  "plans": [
    { "plan_name": "Creator", "tier_order": 1, "price_usd_monthly": 39, "billing_cycle": "monthly", "is_most_popular": 1 }
  ],
  "faq": [
    { "faq_order": 1, "question": "Does Jasper have a free plan?", "answer": "No, trial only." }
  ],
  "radar": [
    { "change_date": "2026-09-01", "change_type": "new-feature", "what_changed": "Launched X.", "confidence": "high", "row_status": "verified" }
  ],
  "cannot_do": [
    { "order_index": 1, "limitation_text": "No fine-tuning on your own corpus." }
  ],
  "relationships": [
    { "target_tool_id": "copy-ai", "relationship_type": "alternative" }
  ]
}
```

#### curl with an Application Password

Create one at **Users → Profile → Application Passwords** (e.g. `aifirstsaas`) and copy the generated secret.

```bash
curl -i -X POST https://YOUR-SITE.example/wp-json/aifirst/v1/tool \
  -u "admin:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H 'Content-Type: application/json' \
  --data-binary @tool.json
```

Re-running the same payload returns `200 OK` and updates in place — this endpoint is idempotent on `tool_id`.

### CSV header (bulk import)

Expected columns (header row, lowercase):

```
title, tool_id, status, excerpt, content,
categories, ai_features,
company_name, website_url, pricing_url, initials, avatar_color, logo_url,
wp_org_slug, subcategory, founded_year, headquarters,
pricing_model, has_free_plan, starting_price_usd, pricing_note,
ai_maturity_score, ai_since, ai_type, ai_on_free_plan,
total_ai_features, native_ai_count, thirdparty_ai_count,
last_ai_update, last_verified,
company_size_smb, company_size_mid, company_size_ent,
notable_customers, best_for, expert_take,
meta_title, meta_description, h1,
plans, faq, radar, cannot_do, relationships
```

- `categories` / `ai_features` — comma- or pipe-separated names.
- `plans`, `faq`, `radar`, `cannot_do`, `relationships` — multi-row cells:
  - row separator: `||`
  - field separator within a row: `~`
  - key/value separator: `=`

Example `plans` cell with two plans:
```
plan_name=Free~tier_order=1~price_usd_monthly=0~billing_cycle=free~is_free=1||plan_name=Pro~tier_order=2~price_usd_monthly=29~billing_cycle=monthly~is_most_popular=1
```

Missing columns are fine — they fall back to the record skeleton defaults.

### API key storage

- Default: stored under `aifirst_settings['api_key']` (option). The Settings page shows only the last 4 characters and uses a `__AIFIRST_KEEP__` sentinel so re-saving the form never leaks the stored value back into the DOM.
- Override: define `AIFIRST_AI_API_KEY` in `wp-config.php` to take the key out of the DB. When set, the Settings page displays "Set via wp-config" and the password field is hidden.
- The key is **never** passed to the browser and **never** sent anywhere other than the configured provider's own auth header.

### Decisions

- **Billing cycle enum narrowed for this session.** Session 1 schema accepted `monthly, annual, one-time, usage-based, custom`. The Session 2 record validator accepts `monthly, annual, one-time, free` as the spec instructs. The underlying `VARCHAR(20)` column will store either set; this is an application-layer tightening only. Logged in Backlog if we want to restore the wider set.
- **`tool_id` is the only join key.** The CPT also stores it as post meta `_aifirst_tool_id`, so finding a post from a `tool_id` is one join rather than a slug match. Session 1 Backlog item about slug drift still stands — if we ever rename a tool, the join column needs rewriting in five tables.
- **Delete-and-insert for child rows.** `plans`, `faq`, `radar`, `cannot_do`, `relationships` are fully replaced on every save. Simpler than diffing and matches how editors think ("the set of plans I just typed is the set of plans"). Consequence: these tables have no durable row ids an external tool can bookmark; if/when we need that, add a stable surrogate key or switch to a per-row upsert.
- **No in-page CSV preview.** Submit → import → results table. Keeps the code small; the results table is the preview.
- **AJAX, not REST, for admin Quick Add.** The admin UI posts to `admin-ajax.php` with the standard admin nonce. REST is reserved for programmatic callers (the future MCP adapter, external tooling). Both still call `AIFirst_Intake::save_record()`.
- **JSON mode for OpenAI, system message for Anthropic.** OpenAI gets `response_format: json_object` so the reply is guaranteed-JSON. Anthropic doesn't have that switch today, so the client strips code fences defensively and falls back to a last-resort "between first `{` and last `}`" extraction. Either way a bad reply returns `WP_Error` with a raw snippet for the UI to show.
- **No form submit button; all saves are AJAX.** The paste-tab form has `<button type="button">` on Save/Publish so the browser's default form submission never fires. Prevents a stray Enter press from reloading the page and losing the draft.
- **Enqueues are screen-scoped** via `admin_enqueue_scripts`'s hook-suffix check — nothing added to assets on screens that don't need it.

### Deferred to Backlog

- **Row-level validator feedback in the admin UI.** Today, validation errors are shown as a bullet list under the result panel. Mapping them to the exact field would be nicer.
- **CSV preview step.** Parse the file, show the first 5 rows + detected columns, then let the user click "Import". Not required for small files.
- **Per-tool update via REST.** Currently only `POST /tool` (idempotent upsert). A `PATCH /tool/{tool_id}` with partial updates is a Session 4 or 5 addition.
- **Rate limiting + queueing.** A big CSV calls the intake one row at a time, synchronously. For hundreds of rows we should chunk via AJAX or move to an async job.
- **Taxonomy term name vs. slug.** The intake assigns terms by name; if two tools disagree on casing ("Writing" vs. "writing") they currently create one term because `term_exists()` is case-insensitive, but it's worth testing when the catalog grows.
- **AI prompt hardening.** The system instruction tells the model to use `""` / `null` / `[]` for unknowns. In practice some models still invent numbers (pricing, scores). Flagged in Session 2 heads-up: check pricing and feature counts by hand on the first batch.
- **Unit tests.** None yet — the record sanitizer and intake upsert are both good candidates.

## Session 1 — Foundation (2026-11-06)

### Decisions

- **Autoloader strategy.** Built a simple SPL autoloader (`AIFirst_Autoloader`) that maps `AIFirst_Foo_Bar` → `includes/class-aifirst-foo-bar.php`. No Composer, no vendor/ — plugin stays dependency-free. Non-class files (activator logic, the main plugin file's bootstrap) load via `require_once` where needed.
- **Activator / deactivator / uninstall split.** Table creation lives in `AIFirst_Activator::activate()`. Deactivation only clears scheduled hooks and flushes rewrites — no data touched. Destructive teardown (DROP TABLE, delete options) lives in `uninstall.php` only, which WordPress runs on plugin delete.
- **Activation notice.** Set an option `aifirst_show_activation_notice` at activation; `AIFirst_Plugin::maybe_show_activation_notice()` reads it on `admin_notices`, prints the one-shot notice, then deletes the option. This survives the redirect after activation (a plain `register_activation_hook` notice does not).
- **`dbDelta` quirks respected.** Two spaces after `PRIMARY KEY`, `KEY` (not `INDEX`), no backticks on names, column defs on their own lines, `CREATE TABLE` not `CREATE TABLE IF NOT EXISTS`. Each table runs as its own `dbDelta()` call so one broken statement cannot block the others.
- **`tool_id` duplicated alongside `post_id`.** Spec keeps `tool_id` (post slug) on every custom table and uses it as the join key in the auxiliary tables (plans, faq, radar, cannot_do, relationships). `tool_meta` stores both so either join path works; a slug change needs a migration pass that rewrites `tool_id` across every table — flagged in Backlog.
- **`uniq_relationship` UNIQUE KEY.** Constraint is `(source_tool_id, target_tool_id, relationship_type)` — two tools can legitimately be both `alternative` and `complement`, so relationship_type is part of the key.
- **CPT `rest_base = 'tools'`.** So the default WP REST endpoint is `/wp-json/wp/v2/tools`, not `/wp-json/wp/v2/aifirst_tool`. Same pattern for taxonomies (`categories`, `ai-features`). The custom `aifirst/v1` namespace is reserved for later endpoints — nothing registered there yet.
- **Rewrite flush.** Done in both activator and deactivator. Activator loads and registers the CPT + taxonomies before flushing, since `register_activation_hook` fires before `init` — without that step the flush writes rules that don't yet include ours.
- **`index.php` silence files** dropped into every folder (includes, admin, public, assets, assets/css, assets/js, templates) in case the host doesn't disable directory indexes.
- **No strict types pragma** in any file this session. Can be added file-by-file later without breaking anything.

### Deferred to Backlog

- **Slug migration path.** If a tool's post slug changes, `tool_id` is stale in five auxiliary tables. Decide whether to switch joins to `post_id` everywhere, add a `save_post` hook that rewrites `tool_id` in place, or both. Needs doing before any public launch where editors can rename tools.
- **Enum validation at the data layer.** Columns like `pricing_model`, `ai_type`, `relationship_type` are VARCHAR today. Validation happens at the admin UI (Session 2) — add CHECK constraints or an application-layer validator if we ever accept external writes.
- **CPT capability model.** Using `capability_type = 'post'` so editors can manage tools. If we want a dedicated role later, switch to a custom capability type and map capabilities on activation.
- **Custom REST endpoints** under `aifirst/v1` (Session 5 or later): featured tool-of-the-week, filtered listings, radar feed.
- **Translation template** (`languages/aifirstsaas.pot`) — generate once strings stabilize.
- **Composer autoload** if we ever pull in a third-party library; until then the hand-rolled autoloader is enough.
- **Uninstall multisite loop.** Current `uninstall.php` only touches the current site's tables. If this plugin ever goes network-active, loop over `get_sites()` and drop per-site tables.
- **DB upgrade path.** `aifirst_db_version` option is written on activate; no upgrade routine yet. First schema change will need a version-compare branch in the activator.
