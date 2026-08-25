<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/encoding.php';
require_once __DIR__ . '/security.php';
apply_utf8_runtime();
security_apply_runtime();
security_configure_session();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
security_apply_headers();
security_csrf_token();
security_enforce_post();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/install.php';
oi_install_ensure();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/live.php';
if (is_file(__DIR__ . '/schedule.php')) {
    require_once __DIR__ . '/schedule.php';
}
require_once __DIR__ . '/paytr.php';
require_once __DIR__ . '/payment.php';
require_once __DIR__ . '/membership.php';
require_once __DIR__ . '/profile.php';
require_once __DIR__ . '/shop.php';
if (is_file(__DIR__ . '/shop_catalog.php')) {
    require_once __DIR__ . '/shop_catalog.php';
}
if (is_file(__DIR__ . '/address.php')) {
    require_once __DIR__ . '/address.php';
}
if (is_file(__DIR__ . '/tests.php')) {
    require_once __DIR__ . '/tests.php';
}
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}
require_once __DIR__ . '/seo_urls.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/catalog.php';
if (is_file(__DIR__ . '/media.php')) {
    require_once __DIR__ . '/media.php';
}
if (is_file(__DIR__ . '/home.php')) {
    require_once __DIR__ . '/home.php';
    ensure_home_schema();
}
if (is_file(__DIR__ . '/groups.php')) {
    require_once __DIR__ . '/groups.php';
}
if (is_file(__DIR__ . '/academy.php')) {
    require_once __DIR__ . '/academy.php';
}
seo_hydrate_route();
maybe_redirect_legacy_url();
ensure_user_profile_schema();
if (function_exists('ensure_catalog_media_schema')) {
    ensure_catalog_media_schema();
}
if (function_exists('ensure_class_groups_schema')) {
    ensure_class_groups_schema();
}
ensure_shop_account_model();
if (function_exists('ensure_address_schema')) {
    ensure_address_schema();
}
if (function_exists('ensure_order_admin_schema')) {
    ensure_order_admin_schema();
}
if (function_exists('ensure_shop_catalog_schema')) {
    ensure_shop_catalog_schema();
}
if (function_exists('ensure_academy_schema')) {
    ensure_academy_schema();
}
if (function_exists('academy_tick_reminders')) {
    academy_tick_reminders();
}
repair_utf8_data();
repair_package_names();
if (is_file(__DIR__ . '/google.php')) {
    require_once __DIR__ . '/google.php';
}
