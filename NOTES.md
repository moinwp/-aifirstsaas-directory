# NOTES — AI First SaaS Directory

Running log of decisions and open items. Dated entries at the top.

## Session 7 — Performance + Schema.org + Design Polish (2026-11-12)

Version bumped to **0.7.0**. `AIFIRST_DB_VERSION` also bumped to 0.7.0 so the upgrader runs `AIFirst_Cache::bump_version()` and all prior transients become orphaned on first admin page load.

Committed in three slices so partial progress survived mid-session: `646603e` (7a), `9124e4f` (7b), this commit (7c).

### Part 7a — Performance caching

- New `AIFirst_Cache` class. Version-counter invalidation: every transient key is `aifirst_v<N>_<raw>`; bumping the `aifirst_cache_version` option by 1 orphans every prior key. Expiry handles cleanup — cheaper than walking `wp_options` to delete every matching transient.
- Hooks:
  - `save_post_aifirst_tool`, `deleted_post` (aifirst_tool), `edited_aifirst_category`, `edited_aifirst_feature` → `flush_tool_caches()`
  - `save_post_aifirst_comparison`, `deleted_post` (aifirst_comparison) → `flush_comparison_caches()`
  - Plugin upgrade (via `AIFirst_Upgrader::maybe_upgrade`) → `bump_version()`
- Wrapped `AIFirst_Query` methods:
  | Method | TTL | Key |
  |---|---|---|
  | `leaderboard` | 1h | `leaderboard_{limit}_{category_id}` |
  | `trending_comparisons` | 1h | `trending_cmp_{limit}` |
  | `comparisons_for_tool` | 1h | `cmp_for_{tool_id}_{limit}` |
  | `category_stats` | 15m | `catstats_{term_id}` |
  | `count_tools_in_category` | 15m | `catcount_{term_id}` |
  | `count_tools_with_feature` | 15m | `featcount_{feature_slug}` |
  | `radar_recent` | 30m | `radar_{md5 of args}` |
- Lazy-loaded home sections via the new public REST route `GET /aifirst/v1/home-section/{section}` (sections: `leaderboard`, `trending`; cached 30m server-side). `window.AIFirstLazy(cfg)` Alpine factory uses `IntersectionObserver` with a 400-px root margin to fetch each section on scroll-into-view. Hero + "This week in AI features" stay inline (LCP-friendly).

### Part 7b — Schema.org JSON-LD

`AIFirst_Schema` emits structured data in `wp_head` priority 20. Every builder reads real stored data; empty fields are omitted, not emitted as empty strings.

| Template | Schema |
|---|---|
| `single-aifirst_tool.php` | `SoftwareApplication` (name, description, applicationCategory, operatingSystem: Web, image, offers from `wp_aifirst_plans`, aggregateRating averaged from G2+Capterra sentiment with summed `reviewCount`, review[] from stored quotes) + `BreadcrumbList` + `FAQPage` |
| `single-aifirst_comparison.php` | `Article` with `about` → `SoftwareApplication[]` (the 2-3 compared tools) + `BreadcrumbList` |
| `taxonomy-aifirst_category.php` | `CollectionPage` + `ItemList` + `BreadcrumbList` + `FAQPage` |
| `taxonomy-aifirst_feature.php` | `CollectionPage` + `ItemList` + `BreadcrumbList` + `FAQPage` |
| `archive-aifirst_tool.php` | `CollectionPage` + `ItemList` + `BreadcrumbList` + `FAQPage` |
| single core post | `BlogPosting` with author, image, dates |

Output uses `wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)`.

### Part 7c — Design polish

Tokens were pulled verbatim from the decoded design artifact (`rootStyle` in the bundler template):

**Light**
```
--aif-bg:     #FAFAF8;
--aif-bg2:    #F3F1EC;
--aif-card:   #FFFFFF;
--aif-border: #E8E6E0;
--aif-text:   #1A1A1A;
--aif-sub:    #6B6B65;
--aif-accent: #2563EB;
--aif-good:   #16A34A;
--aif-warn:   #F59E0B;
--aif-bad:    #DC2626;
```

**Dark** (replaces every surface + text token above)
```
--aif-bg:     #171715;
--aif-bg2:    #1F1F1C;
--aif-card:   #1E1E1B;
--aif-border: #33322D;
--aif-text:   #F3F1EC;
--aif-sub:    #A6A399;
```

Plus typography scale (`--aif-size-h1/h2/h3/base/sm/xs`, weight tokens), spacing (`--aif-space-1` through `--aif-space-8` on an 8-px grid), border-radius tokens, three shadow tokens, and the font-family `Inter, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif`. Inter is enqueued from Google Fonts.

**10-bar maturity**. The design source uses a uniform `#2563EB` fill. To honour both the design *and* the spec's request for a visible gradient:

- Ten independent CSS custom properties `--bar-1` … `--bar-10` default to `var(--aif-accent)`. A theme can swap the ramp in one place by overriding these on `:root`.
- `.aifirst-bars--gradient` opt-in wrapper class ships the red → amber → green ramp (`#DC2626` → `#166534`).
- `AIFirst_Components::maturity_bars()` applies the gradient class by default; strip it from the wrapper class string to go back to uniform accent.

**Dark-mode toggle.** Three-state cycle (System → Light → Dark). Persisted to `localStorage['aifirst_theme']`. FOUC prevention: an inline `<script id="aifirst-fouc">` printed in `wp_head` priority 1 reads localStorage and sets `[data-theme]` on `<html>` before CSS paints. The toggle button is a floating pill at top-right (same corner as the stack badge); at ≤720 px it drops to its own row to avoid overlap. Alpine factory `window.AIFirstThemeToggle(cfg)` handles the state machine.

**Per-template visual audit is deferred.** The design token swap flows through the whole stylesheet because every component already pulls from CSS custom properties, so the site inherits the correct palette, typography, and shadows site-wide. But a per-template pass (hero spacing on `single-aifirst_tool.php`, filter row on `taxonomy-aifirst_category.php`, A-Z jumper on `archive-aifirst_tool.php`, etc.) hasn't been done yet. Tracking as a Session 7d backlog item — purely CSS tweaks, no markup needed.

### Walk-through

1. **Caching** — load home twice within 30 s. Second request's REST responses include `"cached": true`. Save any tool → reload a category page → counts update (invalidation fires via `save_post_aifirst_tool`).
2. **Schema** — `view-source:/tool/<slug>/`, grep `application/ld+json`. Expect three blocks: SoftwareApplication, BreadcrumbList, FAQPage (FAQ block present only when the tool has FAQ rows). Validate in [Google Rich Results Test](https://search.google.com/test/rich-results) after deployment.
3. **Dark mode** — click the top-right pill. Cycles System → Light → Dark. Reload: no flash of light mode when Dark is selected (FOUC script applies the attribute before CSS).
4. **Gradient bars** — `/tool/<slug>/` with maturity 10 shows the full red-to-green ramp. Lower scores show only the first N segments of the ramp.

### Deferred

- **Per-template polish pass (Session 7d).** Token swap is live site-wide, but hero spacing, filter-row density, A-Z jumper, matrix cell colors, and mobile stacking per the design file haven't been aligned pixel-for-pixel. CSS-only work, no markup.
- **Theme toggle in the actual site header.** Ships as a floating pill because no theme hook is portable across classic + block themes.
- **Design's rainbow bar colors verified against decoded source.** The design uses uniform accent; the ramp I shipped is semantically useful but editorial — a future session should pick one per the content team's preference.

## Session 0.6.1 — Hotfix (2026-11-11)

Version bumped to **0.6.1**. No schema changes.

### Changed

- **Share button on `/stack/` was broken.** The old inline `@click` called `navigator.clipboard.writeText()` without `await`ing it and wrapped the rest of the handler in a synchronous `try/catch`, so a rejected promise (insecure context, a user gesture gate, a permission denial) was never caught. The button appeared to succeed but nothing landed in the clipboard, and no fallback ran.
- Moved the Share logic to a factory `window.AIFirstShareStack(config)` in `assets/js/public.js` (same Session 5 hotfix pattern — one JSON-safe `x-data` call in the template). The factory tries three paths in order: (1) `navigator.clipboard.writeText()` with proper `await` in a secure context, (2) legacy `document.execCommand('copy')` on a hidden textarea, (3) `window.prompt()` so the viewer can copy out by hand. Success toasts and 2.5-second **Copied** state fire from the factory, not from the template.
- Audited every other inline multi-line `@click` in `templates/public/stack.php`. Only the **Save this stack** button in URL mode qualified — moved to a parallel factory `window.AIFirstSaveStack(config)` so the slug-replay logic lives in JS, not interpolated PHP. The factory also respects the 50-slug cap and emits a different toast when the cap is hit mid-save. The **Clear stack** button stays inline: it's single-line, all-sync, and only touches the exposed `window.AIFirstStack.clear()` + store state.
- `esc_js()` on a JS string literal was converted to `esc_attr( wp_json_encode( … ) )` in the Clear-stack confirm call for safety.

### How to verify

- `/stack/` with two tools → click **Share this stack** → button flips to **✓ Copied**, toast appears, paste anywhere to see `…/stack/slug+slug/`.
- Same page served from an insecure origin (HTTP) or an older browser → the `execCommand` fallback kicks in; same toast + button flip.
- Clipboard still refused → `window.prompt` appears with the URL pre-filled.
- Open a shared `/stack/slug+slug/` URL in a fresh browser → click **Save this stack** → cookie is written, badge updates. If the shared URL carries more than 50 slugs, the toast says "Saved what fit — stack is full at 50 tools."

## Session 6 — My Stack (2026-11-11)

Version bumped to **0.6.0**. `AIFIRST_DB_VERSION` bumped to 0.6.0 so the upgrader sets the rewrite-flush flag; no schema change this session (`create_tables()` is idempotent).

### Routes

- `/stack/` — reads the `aifirst_stack` cookie, renders the current viewer's stack.
- `/stack/jasper+grammarly+hubspot/` — URL-driven, shareable; parses the `+`-separated slugs, renders that stack without touching the viewer's cookie. The page offers a **Save this stack** button that copies the shared slugs into the cookie.

Both rules are registered in `AIFirst_Public::add_rewrites()`; query var `my_stack_tools` carries the URL segment. `template_include` maps `aifirst_page=stack` → `templates/public/stack.php`.

### Cookie contract

- Name: `aifirst_stack`
- Value: comma-separated `tool_id` slugs, each matched to `^[a-z0-9-]+$`, deduped
- Expiry: 180 days; `path=/`; `SameSite=Lax`; `Secure` only on HTTPS
- Hard cap: 50 entries — the Alpine store ignores adds beyond that and surfaces a toast via the Session 4 `AIFirstToast()` helper
- Mirror: the slug list is also written to `localStorage[aifirst_stack_v1]` so it still restores if the browser dropped the cookie but kept storage

### Alpine wiring

- **Store `aifStack`** — initialised on `alpine:init` from `aifReadStack()`, exposes `slugs`, `count`, `has(slug)`, `add(slug)`, `remove(slug)`. Writes go through `aifWriteStack()` which updates both the cookie and localStorage in one call.
- **Factory `window.AIFirstStackToggle(cfg)`** — the Session 5 hotfix pattern: one small `x-data="AIFirstStackToggle(<?php json ?>)"` call, factory lives in `assets/js/public.js`. Reads `this.$store.aifStack.has(cfg.toolId)` for its `inStack` state and calls `add()`/`remove()` with the right toast.
- **`window.AIFirstStack` helpers** — `read()`, `write()`, `clear()`, `max` — exposed for templates that need to clear the stack (the `/stack/` page's Clear-stack button).

### Components

- `AIFirst_Components::stack_toggle_button( $tool_id, $variant )` — renders the toggle button. `$variant` is `'card'` or `'hero'` for CSS size variants. Caller-safe: no-op when `$tool_id` is empty.
- `AIFirst_Components::stack_header_badge()` — floating pill (top-right, fixed). Injected via `wp_footer` on every plugin-owned screen (new `AIFirst_Public::render_stack_badge()`). `x-show="$store.aifStack.count > 0"` so it hides when the stack is empty.
- `AIFirst_Components::tool_card()` — restructured in 0.6.0: the card is now a `<div>` containing a `<a class="aifirst-tool-card__link">` plus a sibling `<button class="aifirst-stack-btn aifirst-stack-btn--card">` (buttons can't legally nest inside `<a>`). CSS keeps the exterior visual unchanged.

### Query helpers (`AIFirst_Query`)

All DB-side. Each one prepares every variable; each one caps result rows.

- `stack_slugs_from_request()` — single source of truth for "what slugs is this request asking about". Precedence: URL segment > cookie > empty. Returns `{ slugs, source }` (source ∈ `url|cookie|empty`).
- `stack_resolve_tool_ids( $slugs )` — maps slugs → `[ post_id => tool_meta row ]`, order preserved, published tools only.
- `stack_aggregate_features( $input )` — unique features across the stack, native/third-party totals, count of tools with free AI, sum of `starting_price_usd`. Accepts slugs, post_ids, or already-resolved rows.
- `stack_missing_features( $input, $limit )` — features present in the same categories but not in any stack tool, ranked by frequency. Each row also carries up to 3 top candidate tools (by maturity) so the template can render "Try: X, Y, Z".
- `stack_category_gaps( $input, $limit )` — categories not represented, ranked by category tool count. Each row carries the top 3 tools in that category.

### `/stack/` template sections

1. **Your stack** — `tool_card()` grid, empty-state with top-5 popular picks when empty. **Share this stack** copies `/stack/<slugs>/` to clipboard (Alpine + `navigator.clipboard.writeText` with a `window.prompt` fallback on refusal). **Clear stack** button (cookie mode only) confirms, calls `AIFirstStack.clear()`, empties the Alpine store, reloads.
2. **What you're already paying for** — stat strip with unique-feature count / native / third-party / free-AI tools / summed starting prices.
3. **AI features you might be missing** — up to 10 rows, each with feature name, tool count, and "Try: top 3 tools" linking out.
4. **Gaps in your stack** — up to 10 category cards, each linking to the category archive + listing the top 3 tools.

In URL mode, the page shows a **Save this stack** button instead of Clear.

### Why no server-side state

The spec is explicit: no login, no DB table. Per-visitor stack lives entirely in the client (cookie + localStorage mirror). Server reads it only via `$_COOKIE['aifirst_stack']` on request, sanitises every slug with `sanitize_title`, and validates each one against `wp_aifirst_tool_meta` through `stack_resolve_tool_ids()` so a tampered cookie can't cause a bad SQL query or a non-published tool to appear.

### URLs to verify

| Step | URL | Expect |
|---|---|---|
| 1 | `/tools/jasper/` | **+ Add to my stack** in hero → click → button becomes **✓ In your stack**, badge shows "My Stack 1" |
| 2 | `/tools/grammarly/` | Same flow, badge shows "My Stack 2" |
| 3 | `/tools/jasper/` (reload) | Button already reads **✓ In your stack** — cookie persisted |
| 4 | `/stack/` | "Your stack" shows Jasper + Grammarly; stat strip + missing features + gaps rendered |
| 5 | Click **Share this stack** | Clipboard carries `https://…/stack/jasper+grammarly/` |
| 6 | Open that URL in incognito | Same stack, no cookie set (observe `/stack/` in incognito is empty) |
| 7 | In incognito, click **Save this stack** | Cookie set, badge count updates |
| 8 | Click **Clear stack** (cookie mode only) | Confirmation → cookie cleared → empty state with popular picks |
| 9 | Toggle 51 different tools | 51st click is rejected with a "stack is full" toast |

### Deferred / simplified

- **Theme-header link** — spec allows a floating pill as a fallback "if the theme doesn't let you hook it". No theme hook is portable across classic + block themes, so the plugin ships only the floating pill. A theme can still render its own link; the badge coexists.
- **Monthly cost estimate** — summed `starting_price_usd` is a lower-bound estimate (pricing often scales with seats, annual vs monthly, etc.). Surfaced as "Starting at …" so the label matches the honesty of the data.
- **No `aifirst_stack_meta` admin-side analytics** — stacks are client-only by design; if we ever want "N users have Jasper in their stack", we'd add an opt-in, consented endpoint later.

## Session 0.5.1 — Hotfix (2026-11-11)

Version bumped to **0.5.1**. No schema change, no `AIFIRST_DB_VERSION` bump.

### Changed

- **Fix 1 — Blog metabox context.** `AIFirst_Blog::add_metabox()` now registers the "Related AI tools" box with context `'normal'` (was `'side'`). Gutenberg renders `'side'` boxes in a drawer that is sometimes hidden or won't expand; `'normal'` puts the box below the main editor as an always-expanded section.
- Added a defensive `ensure_post_type_support()` on `init` priority 20 that calls `add_post_type_support( 'post', 'custom-fields' )` when a theme / another plugin has stripped it. Core keeps it on by default, so this is belt-and-braces.

### Verified only (no code changes)

- **Fix 2 — "Related comparisons" sidebar.** Already correct. Session 5 stores the comparison tool_ids via `wp_json_encode()`, which produces `["jasper","grammarly"]`. WordPress's `maybe_serialize()` leaves string values alone, so the stored `meta_value` is that same JSON string verbatim — **not** PHP's `a:2:{…}` format. The `LIKE %"<slug>"%` needle in `AIFirst_Query::comparisons_for_tool()` and `comparison_for_tool_pair()` matches correctly. Verified with a round-trip check locally.

### How to verify locally

| Fix | URL | Expected |
|---|---|---|
| 1 | `wp-admin/post-new.php` | "Related AI tools" box appears expanded **below** the main editor |
| 1 | Publish a post with `jasper, grammarly` in the box, then visit it on the frontend | Post renders a "Related AI tools" block at the end with tool cards for both |
| 2 | `/tool/jasper/` | "Related comparisons" sidebar card lists the Jasper vs Grammarly comparison |
| 2 | `/tool/grammarly/` | Same comparison appears |
| 2 | `/tool/mailchimp/` (no comparison references it) | No "Related comparisons" card (block hides when the list is empty) |

## Session 5 — Comparisons, admin inboxes, subscribers, blog (2026-11-09)

Version bumped to **0.5.0**. `AIFIRST_DB_VERSION` is `0.5.0` — the upgrader auto-adds the new `aifirst_subscribers` table + `reviewed_at` columns + backfills `pending → new` on the next admin page load.

### Hotfix (0.4.2)

- Report-modal `x-data` was blowing up because the multiline JS body contained JSON-encoded strings whose double quotes terminated the HTML attribute. Moved the Alpine component to a factory `window.AIFirstReportModal()` in `assets/js/public.js`; `report_modal_trigger()` now passes a JSON-safe config through one small `x-data="AIFirstReportModal(...)"` call.
- Audited every `x-data` in the codebase. The report modal was the only broken one. The newsletter form was simple-literal but we converted it to the same factory pattern (`AIFirstNewsletter`) because Session 5 needed an actual backend anyway. The remaining three (`{ tab: 'features' }`, `{ tab: 'new' }`, `{ open: null }`) are safe and unchanged.

### Carry-over verification

- Compare edge cases (empty / one-tool / same-tool / distinct) — **already correct**, verified only. No changes to the 4.5 implementation.
- Maturity breakdown colors — **already correct**, verified only. `.aifirst-score--green/amber/red` are present in `assets/css/public.css` and the template renders the whitelisted color.

### New: Comparison CPT

- `AIFirst_Comparison` registers `aifirst_comparison` (public, show_in_rest, supports title/editor/revisions/excerpt, rewrite `vs`, menu icon `dashicons-columns`, menu under Tools).
- Post meta (all persisted by the save handler after nonce + capability check + enum validation):
  - `_aifirst_vs_tool_ids` — JSON array of 2–3 slugs; the save handler drops any slug that isn't present in `wp_aifirst_tool_meta` so bogus slugs can't persist.
  - `_aifirst_vs_verdict_budget`
  - `_aifirst_vs_verdict_enterprise`
  - `_aifirst_vs_verdict_use_case_a`
  - `_aifirst_vs_verdict_use_case_b`
- Admin metaboxes `Tools in this comparison` and `Verdicts` on the Comparison edit screen.
- Public: `/vs/<slug>/` renders `templates/public/single-aifirst_comparison.php` (hero, 2–3 tool cards, verdict banner, metric matrix, feature matrix, long-form body).

### Compare runtime integrates curated content

- `templates/public/compare.php` calls `AIFirst_Query::comparison_for_tool_pair()` on the current unique column set. Match is **order-independent** (set equality). On a hit, the verdict banner replaces the Session 4 placeholder and the comparison body appears under "What This Comparison Actually Means" (+ a link to the full `/vs/<slug>/` page). On a miss, the Session 4 placeholder copy stays.

### New query helpers (`AIFirst_Query`)

- `comparisons_for_tool( $tool_id, $limit )` — published comparisons mentioning a tool, newest first. LIKE-match on the JSON meta_value.
- `comparison_for_tool_pair( array $slugs )` — returns the one matching comparison (set equality after sanitising and sorting both sides) or null.
- `trending_comparisons( $limit )` — most recent published comparisons for the home "Trending Comparisons" section.

### New components

- `AIFirst_Components::tool_comparisons_sidebar( $tool_id )` — sidebar card listing comparisons that include the current tool. Rendered on `single-aifirst_tool.php`.
- `AIFirst_Components::trending_comparisons( $limit = 6 )` — home-page rail. Replaces the placeholder block from Session 4.
- `AIFirst_Components::comparison_link_row( $post )` — reusable "A vs B →" row.
- `AIFirst_Components::related_tools_block( $slugs )` — block injected at the end of core WP posts.

### Admin inbox — Reports

- `AIFirst_Reports_Admin` + `AIFirst_Reports_List_Table` under **Tools → Reports** with a `(N)` badge counting `status='new'`.
- Columns: Tool, Kind, Body excerpt (60 chars), Email, Status, Submitted.
- Filters: Status, Tool, Kind. Search over body + email.
- Row actions: Mark reviewed / Mark resolved / Reject / Delete. Bulk actions mirror the row actions.
- Status column backfilled from `pending` → `new` by the upgrader. `reviewed_at` added by dbDelta — the column is nullable; status transitions write `current_time('mysql')` to it.

### Admin inbox — Submissions

- `AIFirst_Submissions_Admin` + `AIFirst_Submissions_List_Table` under **Tools → Submissions** with a `(N)` badge.
- Columns: Kind (new tool / update), Target tool, Body (parsed JSON summary), Email, Status, Submitted.
- Filters: Status, Kind. Search over body + email + tool_id.
- Row actions: Approve / Reject / Delete. Bulk: same.
- **Approve workflow**: for both `new` and `update` submissions, the handler reconstructs a canonical record from the stored JSON (title, website, category, ai_features, notes → tool_features / meta), runs it through `AIFirst_Record::validate()`, then calls `AIFirst_Intake::save_record()` with `status: draft`. On success, the submission row is marked `approved` and `reviewed_at` is stamped. Validation failures flash the exact reasons on the admin page.

### Newsletter subscribers

- New table `{prefix}aifirst_subscribers` created by the upgrader. Columns: `sub_id, email (UNIQUE), scope (all|category|tool|feature), scope_target, source, confirmed, confirmed_at, source_ip, created_at`. Indexes: `uniq_email`, `(scope, scope_target)`, `confirmed`.
- AJAX endpoint `aifirst_subscribe` (anonymous allowed) — nonce, honeypot, 5/hour-per-IP transient throttle. Upserts on email.
- Newsletter form now calls `window.AIFirstNewsletter()` factory from `assets/js/public.js`, posts to the AJAX endpoint, shows a toast on success and an inline error on failure. The form carries hidden `scope` / `scope_target` / `source` fields so a signup from a tool page can be stored with the right scope later (today everything pages pass `all`/`home`; tool/category forms can pass `tool`/<tool_id> etc. when we wire them in).
- Admin page **Tools → Subscribers** — filtered list (scope, confirmed), server-side pagination, and a nonce-protected **Export CSV** button that streams the currently-filtered rows.

### Blog related-tools

- `AIFirst_Blog` adds a sidebar metabox on the core post editor with a comma/newline-separated slug list. Stored in post meta `_aifirst_related_tools` as a JSON slug array (max 10).
- At render time, `the_content` filter appends a "Related AI tools" block when the meta is present on a singular post. Block reuses `AIFirst_Components::tool_card()` so the styling matches the directory pages.
- The plugin's `public.css` is enqueued on blog posts that carry related tools, so the block works on any theme.

### Schema changes (0.5.0)

- `{prefix}aifirst_subscribers` — new table (fields above).
- `{prefix}aifirst_reports` — `status DEFAULT 'new'`; new `reviewed_at DATETIME NULL`. Existing `pending` rows backfilled to `new`.
- `{prefix}aifirst_submissions` — `status DEFAULT 'new'`; new `reviewed_at DATETIME NULL`. Existing `pending` rows backfilled to `new`.
- `AIFirst_Activator::migrate_post_dbdelta()` carries both backfills, idempotent.
- The 0.4.2 → 0.5.0 upgrade requires no reactivation. The upgrader's rewrite-flush flag (added in Session 4) covers the new `/vs/` CPT rewrite.

### Security

Every admin page enforces `manage_options`; every action handler is nonce-protected (`check_admin_referer`). The subscribe AJAX endpoint is anonymous-allowed by design — guarded by nonce + honeypot + per-IP rate limit. Approval workflows validate the canonical record through `AIFirst_Record::validate()` before calling intake. All output escaped (`esc_html`, `esc_url`, `esc_attr`, `wp_kses_post`).

### Known-deferred

- **Double opt-in for subscribers.** The `confirmed` column is populated but we don't send a confirmation email yet. Deferred until we pick a transactional provider.
- **Comparisons admin list column.** Standard WP CPT listing is adequate for now; a dedicated "pending approval" column makes sense when we add editorial review.
- **Blog related-tools metabox UX.** Currently a plaintext slug list. Autocomplete against `wp_aifirst_tool_meta` would be nicer; deferred.

## Session 4.5 — Polish (2026-11-09)

Version bumped to **0.4.1**. No schema changes, no new tables.

### Code changes

- **Fix 1 — tool_card shows the right category.** `AIFirst_Components::tool_card()` now checks `is_tax( 'aifirst_category' )` and, when the viewer is on a category archive, uses the matching term from the tool's categories instead of the alphabetically first one. Falls back to `$cats[0]` on every other screen.
- **Fix 2 — Compare empty / single / same-tool states.** `templates/public/compare.php` now dedupes columns by `tool_id` and only renders the matrix blocks when `$distinct_count >= 2`. When fewer than two distinct tools are resolved it shows "Pick at least two tools to compare."; when the viewer repeated a tool across two slots it shows a flash warning "Pick different tools to compare." and skips the matrices.
- **Fix 3 — Maturity breakdown score color.** `templates/public/single-aifirst_tool.php` now reads `score_color` per row, whitelists it to `green|amber|red`, and emits `.aifirst-score--<color>`. CSS defines the three classes (`--green/#15C39A`, `--amber/#F59E0B`, `--red/#EF4444`). Unknown / empty values fall back to amber.
- **Fix 5 — Breadcrumb includes the category.** The tool page breadcrumb is now `Home / Tools / <first category (link)> / <tool title>`. The category link comes from the tool's first category by term order — `get_the_terms()` hands them back in that order and `$cat` was already computed at the top of the template for the hero pill, so no extra query.
- **Fix 6 — Report-a-change modal switches to AJAX.** The modal now intercepts submit, posts to `admin-ajax.php` with action `aifirst_submit_report`, closes on success, and fires a transient toast "Thanks — your report was recorded." via the new `window.AIFirstToast()` helper. New `AIFirst_Reports::handle_ajax()` handler reuses the same nonce (bound to the tool), honeypot, and 60-s-per-IP throttle as the admin-post handler and talks JSON via `wp_send_json_success/error()`. The admin-post handler stays as a no-JS graceful fallback. Enum changed to the Session 4.5 spec: `wrong_info, pricing_changed, feature_added, feature_removed, other` (column is `VARCHAR(60)`, no migration needed).

### Verified-only (no code changes)

- **Fix 4 — "Same company" sidebar.** Already correct in Session 4. The block is wrapped in `<?php if ( ! empty( $same_co ) ) : ?>`, so the entire card is skipped when no siblings share the current tool's `company_group_id`. The "hide entirely when there are no siblings" choice from the spec was already in place.

### URLs to click to verify

| Fix | URL | Expected |
|---|---|---|
| 1 | `/category/email-marketing/` (or whatever slug you have) | Each card's sub-label shows the queried category, not `Content Marketing` |
| 2a | `/compare/` | Empty selectors + "Pick at least two tools to compare." No matrices |
| 2b | `/compare/jasper/jasper/` | Warning banner + skipped matrices |
| 2c | `/compare/jasper/grammarly/` | Full metric matrix + feature matrix |
| 3 | `/tool/jasper/` | AI Maturity breakdown rows render in mixed green/amber/red per `score_color` |
| 4 | `/tool/mailchimp/` | Sidebar's "Same company" block appears only when a sibling exists |
| 5 | `/tool/jasper/` | Breadcrumb reads `Home / Tools / <category> / Jasper`, category is clickable |
| 6 | `/tool/jasper/` | Click "Report a change" → modal opens → submit → modal closes + toast appears |

## Session 4 — Public frontend (2026-11-08)

Version bumped to **0.4.0**. Every page in the design now renders from the plugin.

### What shipped

| Route | Template | Data it reads |
|---|---|---|
| `/` (opt-in) | `front-page.php` | counters, radar last 14 d, leaderboard, universal features, home FAQ |
| `/tools/` | `archive-aifirst_tool.php` | CPT archive with filter sidebar + A–Z jumper |
| `/tools/:slug/` | `single-aifirst_tool.php` | tool_meta + 6 child tables + relationships + same-company siblings |
| `/categories/:slug/` | `taxonomy-aifirst_category.php` | category_meta + CPT list + radar sidebar |
| `/features/` | `features-index.php` | aifirst_feature grouped by `feature_meta.feature_type` |
| `/ai-features/:slug/` | `taxonomy-aifirst_feature.php` | feature_meta + tools_with_feature + tools_without_feature |
| `/changelog/` | `changelog.php` | radar table with tool/type/period filters + subscribe |
| `/compare/:a/:b/:c?` | `compare.php` | metric matrix + feature matrix across up to three tools |
| `/submit/` | `submit.php` | two-tab form posting to `admin-post.php` → `aifirst_submissions` |
| Overlay | `single-aifirst_tool.php` modal | form posting to `admin-post.php` → `aifirst_reports` |

Templates live in `templates/public/`. Themes override by placing a file at `theme/aifirst/<same-filename>` — the dispatcher checks `locate_template()` before falling back to the plugin copy.

### New plumbing

- `AIFirst_Public` — registers the four pretty-URL rewrites (`/changelog/`, `/submit/`, `/compare/*`, `/features/`), four query vars (`aifirst_page`, `aifirst_cmp_a/b/c`), the `template_include` filter, and conditional asset enqueue (only on plugin-owned screens).
- `AIFirst_Query` — every read-side SQL call the templates need, in one place. Each method takes a plain argument and returns rows; no inline `$wpdb->prepare()` in templates.
- `AIFirst_Components` — reusable render functions. Public API: `maturity_bars()`, `free_ai_badge()`, `plan_pill()`, `category_pill()`, `tool_card()`, `faq_block()`, `avatar()`, `breadcrumbs()`, `newsletter_form()`, `report_modal_trigger()`, `date_display()`.
- `AIFirst_Submissions` — processes the `/submit/` form. Writes to the new `aifirst_submissions` table; nonce + honeypot + 60-s-per-IP throttle.
- `AIFirst_Reports` — processes the Report-a-change modal on tool pages. Writes to the new `aifirst_reports` table with the same security envelope.

### Template override pattern

```
# plugin ships
wp-content/plugins/aifirstsaas-directory/templates/public/single-aifirst_tool.php

# theme can override by placing
wp-content/themes/my-theme/aifirst/single-aifirst_tool.php
```

`template_include` consults `locate_template( array( 'aifirst/<basename>' ) )` first and falls back to the plugin copy. Overrides work on any theme (classic or block) because the dispatcher returns a template path — WordPress still renders it inside the current theme's `get_header()` / `get_footer()`.

### New tables

| Table | Purpose |
|---|---|
| `aifirst_submissions` | New-tool / update-existing form submissions. `(kind, tool_id, body JSON, email, status, source_ip, created_at)` — indexed on `status, created_at` and `tool_id`. |
| `aifirst_reports` | Change-report modal entries. `(tool_id, kind, body, email, status, source_ip, created_at)` — indexed on `(tool_id, created_at)` and `status`. |

Both carry an IP for abuse review. Neither exposes any admin UI yet — Session 5 will add a moderation screen.

### Front page is opt-in

The dispatcher only intercepts the home page when `get_option( 'aifirst_own_home' )` is truthy. Otherwise the theme's home runs as-is. To enable: in wp-admin, `Tools → All Tools → …` isn't the right place yet — for now flip the option directly:

```bash
wp option update aifirst_own_home 1
```

A proper toggle on the Settings page lands in Session 5.

### Rewrite flush

The 0.3.0 upgrader now also stores an `aifirst_needs_rewrite_flush = 1` flag, which `AIFirst_Public::maybe_flush_rewrites()` consumes on the next `init` (priority 99) and clears. One extra flush per upgrade. After that the new `/compare/`, `/submit/`, `/changelog/`, `/features/` rules are bound.

### Assets

- `assets/css/public.css` — tokens (`--aif-bg`, `--aif-bg2`, `--aif-card`, `--aif-border`, `--aif-text`, `--aif-sub`, `--aif-accent`, plus `--aif-good/warn/bad`), both light and dark via `prefers-color-scheme` **and** an explicit `data-theme="dark|light"` override. Component styles for every reusable piece. Mobile breakpoints at 1100/960/720/600.
- `assets/js/public.js` — tiny companion to Alpine: clears URL flash params after 3s and provides `AIFirstGoCompare( a, b, c )` for the compare selector.
- `alpinejs@3.14.1` loaded from `cdn.jsdelivr.net` with `defer`. No build step, no React.

### Decisions

- **Dispatcher, not the WP template hierarchy, decides routing.** `template_include` short-circuits with a plugin file for our screens, so themes don't need to know about the CPT/taxonomy. Themes that want to override still can — the override check runs first.
- **Taxonomy slug stays at `ai-features`.** Changing it to `features` would collide with the `/features/` index rewrite. The design calls the top-level section "AI Features" and hits `/features/` for the index; the detail pages live under `/ai-features/<slug>/`. Documented here so later sessions don't try to "fix" it.
- **Compare is query-driven in Session 4.** No curated comparison CPT yet. The page renders a metric matrix + the union of features across the picked tools. Curated long-form comparison pages (and the home "Trending Comparisons" list) land in Session 5.
- **Alpine for interactivity.** Zero build chain, CDN-loaded, defer-attributed. Used by: tool-page tab bar, FAQ accordion, report modal, submit-form tab toggle. Any filter today is POST-to-GET form submit — good enough for the current scale (dataset is small, and server rendering keeps crawlable URLs).
- **Submissions and reports are public forms.** Bound to nonces + honeypot + IP throttle; no capability check (otherwise logged-out visitors couldn't submit). The REST write path (`POST /aifirst/v1/tool`) stays `manage_options`-only and is untouched.
- **`aifirst_own_home` guards the homepage.** On a site where the directory is the whole site, the plugin owns `/`. On a site where the plugin lives inside a bigger brand, the theme's home still runs. One line to flip.
- **No shortcode this session.** The front-page template handles the home. If a shortcode is needed for mid-page composition in Session 5 we can wrap the same components — they're all callable statics.

### Backlog for Session 5

- Admin moderation screens for submissions + reports (list, approve, reject, archive).
- Curated comparison CPT with long-form "What This Comparison Actually Means" copy + related comparisons.
- Blog using core WP Posts with a `blog_category` taxonomy and an `aifirst_related_tools` post meta.
- My Stack derived view (depends on richer feature data per tool).
- Newsletter subscriber persistence (currently the form fires but doesn't save).
- Settings-page toggle for `aifirst_own_home`.
- Full-text search / indexed search for tools + features + categories.

## Session 3 — Design-aligned schema expansion (2026-11-08)

Version bumped to **0.3.0**. Scope: close every schema gap between the plugin and the published design, plus the four tool-shape items pulled forward from Session 4 so the tool contract is now frozen.

### New tables (all `{wpdb->prefix}aifirst_…`)

| Table | Purpose | Key cols |
|---|---|---|
| `tool_features` | The rich per-tool feature join. A taxonomy term tags a tool with a feature; this row says *at what plan, status, since when, native or 3rd-party*. | `(tool_id, feature_slug)` unique |
| `maturity_breakdown` | Per-criterion rows that back the "AI Maturity breakdown" card on the tool page. | `(tool_id, order_index)` |
| `industries` | Industries served, with a percentage per row. Backs the "Top industries" bars on the tool page. | `(tool_id, order_index)` |
| `sentiment` | Review-source scores (G2, Capterra, …) with review counts and the deep-link URL. | `(tool_id, source)` unique |
| `quotes` | Short user quotes with speaker role, for the Sentiment tab. | `(tool_id, order_index)` |
| `category_meta` | Long-form content for the Category detail page (icon, intro, what AI does well, what's immature, honest observation, most-common AI, fastest-growing, buyer's checklist JSON). | `term_id` unique |
| `feature_meta` | Long-form content for the Feature detail page (color, universal vs category-specific, description, why it matters, evaluate[], related features[], how it works surface/deeper, vendors-don't-tell-you[], buyer's checklist[]). | `term_id` unique |

### Column changes on existing tables

- **`tool_meta.company_size_smb / _mid / _ent`** — type changed from `TINYINT(1)` boolean to `TINYINT UNSIGNED` percentage (0–100). The three fields together represent the company-size distribution the design renders as horizontal bars.
- **`tool_meta.company_group_id`** — new `VARCHAR(191) NOT NULL DEFAULT ''` with its own index. A free-form slug (e.g. `atlassian`) shared across sibling tools made by the same company. Backs the "Same company" sidebar block on the tool page.
- **`cannot_do.workaround`** — new `TEXT NULL`. The design shows a short grey workaround line under every `✗` limitation.

### DB upgrade path

New `AIFirst_Upgrader` class runs on `plugins_loaded` priority 5. It compares the stored `aifirst_db_version` option with the `AIFIRST_DB_VERSION` constant and, if behind, calls `AIFirst_Activator::create_tables()` + `migrate_post_dbdelta()`. Both routines are idempotent: `create_tables()` is pure `dbDelta`, `migrate_post_dbdelta()` issues defensive `ALTER TABLE MODIFY COLUMN` for the three `company_size_*` fields in case dbDelta's type-change path missed them.

This means: from 0.2.0 to 0.3.0, the user does **nothing** — the first admin page load after `git pull` runs the migration. No reactivation required.

### Canonical record contract (0.3.0)

Top-level keys of a record are now:

```
tool, meta, plans, faq, radar, cannot_do, relationships,
tool_features, maturity_breakdown, industries, sentiment, quotes,
category_meta, feature_meta
```

- `tool.ai_features` (the flat tag list) is still accepted but is **auto-derived from `tool_features`** when both are present — the sanitizer merges the names out of `tool_features` back into `ai_features` so the taxonomy relationship still fires.
- `meta.company_group_id` — slug-sanitized.
- `meta.company_size_{smb,mid,ent}` — integers 0–100 (clamped). Formerly 0/1.
- `meta.expert_take` — upgraded from `sanitize_textarea_field` to `wp_kses_post` so the design's 3-paragraph HTML survives.
- `cannot_do[].workaround` — new `TEXT`.
- Radar `change_type` accepts **design labels as aliases** (`"New Feature"`, `"Plan Change"`, `"Removed"`) in addition to the canonical kebab-case (`new-feature`, `pricing-change`, `deprecated`). Storage is always canonical; display re-labels at the frontend.
- `tool_features[]` entries: `{feature_name, feature_slug (auto), plan (Free|Starter|Pro|Enterprise|Add-on), status (stable|beta|alpha|deprecated|coming-soon), is_native (0|1), since, feature_note}`. Dedup by `feature_slug` — last one wins.
- `maturity_breakdown[]` entries: `{order_index, criterion, score_display, score_numeric, score_color}`.
- `industries[]` entries: `{order_index, name, pct}`.
- `sentiment[]` entries: `{source, score (0–5), review_count, source_url}`.
- `quotes[]` entries: `{order_index, quote_text, role}`.
- `category_meta[]` entries: `{term, icon, intro, what_ai_does_well, whats_immature, honest_observation, most_common_ai, fastest_growing, buyers_checklist[]}`. Buyer's checklist is stored as JSON. Terms are created in `aifirst_category` if missing.
- `feature_meta[]` entries: `{term, color (#RRGGBB), feature_type (universal|category-specific), description, why_matters, evaluate[], related_features[], how_it_works_surface, how_it_works_deeper, vendors_dont_tell_you[], buyers_checklist[]}`. Array fields stored as JSON. Terms are created in `aifirst_feature` if missing.

### Updated CSV header

Add these columns to the end of the Session 2 header:

```
company_group_id,
tool_features, maturity_breakdown, industries, sentiment, quotes,
category_meta, feature_meta
```

Multi-row cells use the same `||` row / `~` field / `=` key-value syntax as Session 2.

Example `tool_features` cell with two features:
```
feature_name=Lead Scoring~plan=Pro~status=stable~is_native=1~since=2023||feature_name=Call Summary~plan=Enterprise~status=beta~is_native=1~since=2026-09
```

Example `industries` cell:
```
name=SaaS~pct=40||name=Agencies~pct=25||name=E-commerce~pct=20||name=Other~pct=15
```

Example `category_meta` cell (nests a JSON-array via the pipe syntax — the sanitizer accepts arrays, newline strings, or pipe strings):
```
term=HR SaaS~icon=👥~what_ai_does_well=Resume parsing...~whats_immature=Bias auditing...~buyers_checklist=Discloses model?|Human review?|Bias audit public?
```

### Updated AI parse prompt

The system instruction now describes every new top-level key with its field shape. `tool_features` is explicitly flagged as the richer path (plan / status / native / since) and `ai_features` is noted as optional because it gets auto-derived. Percentages for company size and industries are explained (industries sum to ~100). `expert_take` is marked as HTML-allowed.

### Decisions

- **Terms created at intake.** When `tool_features` or `*_meta` references a feature/category by name, the term is created in `aifirst_feature` / `aifirst_category` on the fly. Keeps the intake single-pass; the cost is that typos create one-off terms. Mitigated by a de-dup step (`sanitize_term_list` + `term_exists` resolving by name first, slug second).
- **No separate tool_groups table.** Per your call. `tool_meta.company_group_id` is a free-form slug. Gives us the "Same company" block without a second join. Downside: no company metadata (website, logo) — the design doesn't show any, so we don't need one yet.
- **Radar enum stays canonical.** We accept design labels as input aliases but never store them. One canonical set in the DB; display translation happens at the frontend. Keeps queries clean and makes both the admin UI and existing Session 2 data valid.
- **Term content in dedicated tables, not `wp_termmeta`.** We need schema (indexed `feature_type`, consistent columns) and the freedom to JSON-encode the array fields. `wp_termmeta` works but every read becomes a lookup-by-key. Belt-and-braces: `uniq_term` on `term_id` means only one meta row per term.
- **Admin repeaters inflate the form.** The Quick Add page now has 10 repeaters. That's a lot to scroll. We accept the cost in Session 3 to lock the contract; in Session 4 we can either collapse sections by default or split the review form into tabs that mirror the Tool detail page's tab bar.
- **Dedup on tool_features.** Two rows with the same `feature_slug` are collapsed — the last wins. Repeating a feature by accident doesn't break the unique-index insert.
- **ai_features still works stand-alone.** Users who don't want to fill plan/status/since can keep typing names in the simple "AI features (comma-separated)" field. The structured path is additive.

### Deferred (unchanged from the Session 4+ list, minus the four pulled forward)

Everything else from my earlier Session 4/5 list stays where it is: comparison long-form content, change-report submissions, submission intake, newsletter subscribers, blog (core WP Posts), My Stack view, derived home-page sections.

### Backlog carried from Session 1/2

- Slug-drift migration (`tool_id` rewrites across child tables).
- Composer autoload.
- Multisite uninstall loop.
- Translation `.pot`.
- CSV preview + chunked import for very large files.

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
