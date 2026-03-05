# TitoNova Cloud Engine Pro (WordPress Plugin)

WordPress-side multi-tenant backend for TitoNova Cloud Engine Pro.

## What this plugin provides

- Per-user tenant isolation (`1 WordPress user = 1 tenant`)
- Custom WordPress database tables (created on activation)
- Secure REST API routes for tenant/widget CRUD
- Local database only (`$wpdb`, no external DB required)
- LocalWP-compatible architecture

## Tables created on activation

- `{wp_prefix}tnova_tenants`
- `{wp_prefix}tnova_widgets`

## REST API routes

Base namespace: `/wp-json/titonova/v1`

- `GET /tenant/me`
- `GET /widgets`
- `POST /widgets` (create/update)
- `POST /widgets/reorder`
- `DELETE /widgets/{widget_uuid}`

Project scoping (optional):

- `project_id` query/body param (defaults to `default`)

All routes require an authenticated WordPress user (`current_user_can('read')`).

## LocalWP install

1. Copy folder `wordpress-plugin/titonova-cloud-engine-pro` into your site:
   - `app/public/wp-content/plugins/titonova-cloud-engine-pro`
2. In WP Admin, activate **TitoNova Cloud Engine Pro**.
3. Sign in as a normal WP user.
4. Test:
   - `GET /wp-json/titonova/v1/tenant/me`
   - `GET /wp-json/titonova/v1/widgets`

For browser requests from your app, include:

- WP auth cookies
- `X-WP-Nonce` (from `wp_create_nonce('wp_rest')`)

## Security notes

- Route permissions enforce signed-in users.
- Basic request throttling (per user + route) is enabled with transients.
- Widget code is sanitized unless user has `unfiltered_html`.
