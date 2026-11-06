# AI First SaaS Directory

Data and presentation layer for [aifirstsaas.com](https://aifirstsaas.com) — an independent directory cataloging AI features across SaaS tools.

## Status

Session 1 (foundation) complete. The plugin:

- Activates cleanly and creates 6 custom tables via `dbDelta()`
- Registers the `aifirst_tool` custom post type (public slug: `tools`)
- Registers `aifirst_category` (hierarchical) and `aifirst_feature` (flat) taxonomies
- Exposes everything to the REST API under namespace `wp/v2` via `show_in_rest = true`

Not yet built: admin metaboxes, CSV importer, frontend templates, assets, REST endpoints beyond the automatic ones.

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
