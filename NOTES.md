# NOTES — AI First SaaS Directory

Running log of decisions and open items. Dated entries at the top.

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
