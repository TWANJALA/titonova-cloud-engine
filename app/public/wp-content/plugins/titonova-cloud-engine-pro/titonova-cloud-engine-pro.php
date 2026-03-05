<?php
/**
 * Plugin Name: TitoNova Cloud Engine Pro
 * Description: Multi-tenant widget SaaS backend for WordPress users with secure REST APIs and local-database storage.
 * Version: 1.0.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: TitoNova
 * License: GPLv2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

final class TitoNova_Cloud_Engine_Pro {
    private const REST_NAMESPACE = 'titonova/v1';
    private const TENANT_KEY_PREFIX = 'tnova_';
    private const RATE_LIMIT_PER_MINUTE = 120;

    public static function init() {
        self::ensure_schema();
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);
    }

    public static function activate() {
        self::ensure_schema();
    }

    private static function ensure_schema() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $tenants_table = self::tenants_table();
        $widgets_table = self::widgets_table();

        $sql_tenants = "CREATE TABLE {$tenants_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            tenant_key VARCHAR(80) NOT NULL,
            business_name VARCHAR(191) DEFAULT '' NOT NULL,
            plan_tier VARCHAR(32) DEFAULT 'free' NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            UNIQUE KEY tenant_key (tenant_key)
        ) {$charset};";

        $sql_widgets = "CREATE TABLE {$widgets_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT(20) UNSIGNED NOT NULL,
            project_key VARCHAR(80) NOT NULL DEFAULT 'default',
            widget_uuid VARCHAR(80) NOT NULL,
            name VARCHAR(191) NOT NULL,
            code LONGTEXT NOT NULL,
            display_order INT(10) UNSIGNED DEFAULT 0 NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY tenant_project_widget_uuid (tenant_id, project_key, widget_uuid),
            KEY tenant_project_order (tenant_id, project_key, display_order)
        ) {$charset};";

        dbDelta($sql_tenants);
        dbDelta($sql_widgets);
    }

    public static function register_rest_routes() {
        register_rest_route(self::REST_NAMESPACE, '/auth/nonce', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'handle_auth_nonce'],
            'permission_callback' => [__CLASS__, 'check_permission'],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/tenant/me', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'handle_tenant_me'],
            'permission_callback' => [__CLASS__, 'check_permission'],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/widgets', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [__CLASS__, 'handle_widgets_list'],
                'permission_callback' => [__CLASS__, 'check_permission'],
                'args' => [
                    'project_id' => [
                        'required' => false,
                        'sanitize_callback' => [__CLASS__, 'sanitize_project_key'],
                    ],
                ],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [__CLASS__, 'handle_widgets_save'],
                'permission_callback' => [__CLASS__, 'check_permission'],
                'args' => [
                    'widget_uuid' => [
                        'required' => false,
                        'sanitize_callback' => [__CLASS__, 'sanitize_widget_uuid'],
                    ],
                    'project_id' => [
                        'required' => false,
                        'sanitize_callback' => [__CLASS__, 'sanitize_project_key'],
                    ],
                    'name' => [
                        'required' => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'code' => [
                        'required' => true,
                    ],
                ],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/widgets/reorder', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [__CLASS__, 'handle_widgets_reorder'],
            'permission_callback' => [__CLASS__, 'check_permission'],
            'args' => [
                'widget_uuids' => [
                    'required' => true,
                ],
                'project_id' => [
                    'required' => false,
                    'sanitize_callback' => [__CLASS__, 'sanitize_project_key'],
                ],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/widgets/(?P<widget_uuid>[a-zA-Z0-9\-_]+)', [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => [__CLASS__, 'handle_widgets_delete'],
            'permission_callback' => [__CLASS__, 'check_permission'],
            'args' => [
                'widget_uuid' => [
                    'required' => true,
                    'sanitize_callback' => [__CLASS__, 'sanitize_widget_uuid'],
                ],
                'project_id' => [
                    'required' => false,
                    'sanitize_callback' => [__CLASS__, 'sanitize_project_key'],
                ],
            ],
        ]);
    }

    public static function check_permission(WP_REST_Request $request) {
        if (!is_user_logged_in() || !current_user_can('read')) {
            return new WP_Error('tnova_forbidden', 'You must be signed in to access this endpoint.', ['status' => 401]);
        }

        if (!self::check_rate_limit(get_current_user_id(), $request->get_route())) {
            return new WP_Error('tnova_rate_limited', 'Too many requests. Please retry shortly.', ['status' => 429]);
        }

        return true;
    }

    private static function check_rate_limit(int $user_id, string $route): bool {
        $window = intdiv(time(), 60);
        $key = sprintf('tnova_rl_%d_%s_%d', $user_id, md5($route), $window);
        $count = (int) get_transient($key);

        if ($count >= self::RATE_LIMIT_PER_MINUTE) {
            return false;
        }

        set_transient($key, $count + 1, 90);
        return true;
    }

    public static function handle_tenant_me() {
        $tenant = self::get_or_create_tenant(get_current_user_id());
        if (is_wp_error($tenant)) {
            return $tenant;
        }

        $user = wp_get_current_user();
        return new WP_REST_Response([
            'ok' => true,
            'tenant' => $tenant,
            'user' => [
                'id' => (int) $user->ID,
                'email' => (string) $user->user_email,
                'display_name' => (string) $user->display_name,
            ],
        ]);
    }

    public static function handle_auth_nonce() {
        return new WP_REST_Response([
            'ok' => true,
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
    }

    public static function handle_widgets_list(WP_REST_Request $request) {
        global $wpdb;
        $tenant = self::get_or_create_tenant(get_current_user_id());
        if (is_wp_error($tenant)) {
            return $tenant;
        }
        $project_key = self::sanitize_project_key($request->get_param('project_id'));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT widget_uuid, project_key, name, code, display_order, created_at, updated_at
                 FROM " . self::widgets_table() . "
                 WHERE tenant_id = %d AND project_key = %s
                 ORDER BY display_order ASC, id ASC",
                (int) $tenant['id'],
                $project_key
            ),
            ARRAY_A
        );

        return new WP_REST_Response([
            'ok' => true,
            'tenant_id' => (int) $tenant['id'],
            'project_id' => $project_key,
            'widgets' => $rows ?: [],
        ]);
    }

    public static function handle_widgets_save(WP_REST_Request $request) {
        global $wpdb;
        $tenant = self::get_or_create_tenant(get_current_user_id());
        if (is_wp_error($tenant)) {
            return $tenant;
        }

        $widget_uuid = self::sanitize_widget_uuid($request->get_param('widget_uuid'));
        if ($widget_uuid === '') {
            $widget_uuid = self::sanitize_widget_uuid(wp_generate_uuid4());
        }
        $project_key = self::sanitize_project_key($request->get_param('project_id'));

        $name = sanitize_text_field((string) $request->get_param('name'));
        $raw_code = (string) $request->get_param('code');
        $code = self::sanitize_widget_code($raw_code);

        if ($name === '' || $code === '') {
            return new WP_Error('tnova_invalid_payload', 'Widget name and code are required.', ['status' => 400]);
        }

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM " . self::widgets_table() . " WHERE tenant_id = %d AND project_key = %s AND widget_uuid = %s",
                (int) $tenant['id'],
                $project_key,
                $widget_uuid
            ),
            ARRAY_A
        );

        $now = current_time('mysql', true);
        if ($existing) {
            $wpdb->update(
                self::widgets_table(),
                [
                    'name' => $name,
                    'code' => $code,
                    'updated_at' => $now,
                ],
                ['id' => (int) $existing['id']],
                ['%s', '%s', '%s'],
                ['%d']
            );
        } else {
            $max_order = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COALESCE(MAX(display_order), -1) FROM " . self::widgets_table() . " WHERE tenant_id = %d AND project_key = %s",
                    (int) $tenant['id'],
                    $project_key
                )
            );
            $wpdb->insert(
                self::widgets_table(),
                [
                    'tenant_id' => (int) $tenant['id'],
                    'project_key' => $project_key,
                    'widget_uuid' => $widget_uuid,
                    'name' => $name,
                    'code' => $code,
                    'display_order' => $max_order + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
            );
        }

        return self::handle_widgets_list($request);
    }

    public static function handle_widgets_reorder(WP_REST_Request $request) {
        global $wpdb;
        $tenant = self::get_or_create_tenant(get_current_user_id());
        if (is_wp_error($tenant)) {
            return $tenant;
        }

        $widget_uuids = $request->get_param('widget_uuids');
        if (!is_array($widget_uuids) || count($widget_uuids) === 0) {
            return new WP_Error('tnova_invalid_payload', 'widget_uuids must be a non-empty array.', ['status' => 400]);
        }
        $project_key = self::sanitize_project_key($request->get_param('project_id'));

        $order = 0;
        foreach ($widget_uuids as $raw_uuid) {
            $widget_uuid = self::sanitize_widget_uuid($raw_uuid);
            if ($widget_uuid === '') {
                continue;
            }
            $wpdb->update(
                self::widgets_table(),
                ['display_order' => $order, 'updated_at' => current_time('mysql', true)],
                ['tenant_id' => (int) $tenant['id'], 'project_key' => $project_key, 'widget_uuid' => $widget_uuid],
                ['%d', '%s'],
                ['%d', '%s', '%s']
            );
            $order++;
        }

        return self::handle_widgets_list($request);
    }

    public static function handle_widgets_delete(WP_REST_Request $request) {
        global $wpdb;
        $tenant = self::get_or_create_tenant(get_current_user_id());
        if (is_wp_error($tenant)) {
            return $tenant;
        }

        $widget_uuid = self::sanitize_widget_uuid($request->get_param('widget_uuid'));
        if ($widget_uuid === '') {
            return new WP_Error('tnova_invalid_payload', 'widget_uuid is required.', ['status' => 400]);
        }
        $project_key = self::sanitize_project_key($request->get_param('project_id'));

        $wpdb->delete(
            self::widgets_table(),
            ['tenant_id' => (int) $tenant['id'], 'project_key' => $project_key, 'widget_uuid' => $widget_uuid],
            ['%d', '%s', '%s']
        );

        return self::handle_widgets_list($request);
    }

    private static function sanitize_widget_code(string $code): string {
        $trimmed = trim($code);
        if ($trimmed === '') {
            return '';
        }

        if (current_user_can('unfiltered_html')) {
            return $trimmed;
        }

        return wp_kses_post($trimmed);
    }

    public static function sanitize_widget_uuid($value): string {
        $safe = sanitize_key((string) $value);
        return substr($safe, 0, 80);
    }

    public static function sanitize_project_key($value): string {
        $safe = sanitize_key((string) $value);
        if ($safe === '') {
            return 'default';
        }
        return substr($safe, 0, 80);
    }

    private static function get_or_create_tenant(int $user_id) {
        global $wpdb;
        if ($user_id <= 0) {
            return new WP_Error('tnova_invalid_user', 'Invalid user context.', ['status' => 401]);
        }

        $table = self::tenants_table();
        $tenant = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE user_id = %d LIMIT 1", $user_id),
            ARRAY_A
        );

        if ($tenant) {
            return [
                'id' => (int) $tenant['id'],
                'user_id' => (int) $tenant['user_id'],
                'tenant_key' => (string) $tenant['tenant_key'],
                'business_name' => (string) $tenant['business_name'],
                'plan_tier' => (string) $tenant['plan_tier'],
            ];
        }

        $user = get_userdata($user_id);
        $tenant_key = self::TENANT_KEY_PREFIX . wp_generate_password(18, false, false);
        $now = current_time('mysql', true);

        $wpdb->insert(
            $table,
            [
                'user_id' => $user_id,
                'tenant_key' => $tenant_key,
                'business_name' => $user ? (string) $user->display_name : '',
                'plan_tier' => 'free',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s']
        );

        $tenant_id = (int) $wpdb->insert_id;
        return [
            'id' => $tenant_id,
            'user_id' => $user_id,
            'tenant_key' => $tenant_key,
            'business_name' => $user ? (string) $user->display_name : '',
            'plan_tier' => 'free',
        ];
    }

    private static function tenants_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'tnova_tenants';
    }

    private static function widgets_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'tnova_widgets';
    }
}

register_activation_hook(__FILE__, ['TitoNova_Cloud_Engine_Pro', 'activate']);
add_action('plugins_loaded', ['TitoNova_Cloud_Engine_Pro', 'init']);
