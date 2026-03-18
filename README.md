# Composer Compatibility Enforcer

Removes selected namespaces and classes from plugin Composer autoloaders to avoid vendor conflicts.

This is primarily useful in Bedrock projects running Roots Acorn, where multiple Composer autoloaders can introduce conflicting versions of shared libraries. For background, see
the [Acorn compatibility notes](https://roots.io/acorn/docs/compatibility/).

This MU plugin aims to provide a cleaner alternative to often brittle Composer patches. It does not ship with default rules; configure it via a WordPress filter.

## Requirements

- PHP 8.1+
- WordPress 5.9+
- Composer-based WordPress installation (e.g., Bedrock)

## Installation

```bash
composer require unloc/composer-compatibility-enforcer
```

The package type is `wordpress-muplugin`, so it will automatically install to `web/app/mu-plugins/` on Bedrock setups.

## Configuration

This plugin ships with no default rules. Add rules via the `composer_compatibility_enforcer_rules` filter.

Since this is an MU plugin, the filter must be registered in code that loads before or during `plugins_loaded` (priority < 11). The recommended approach is a separate MU plugin file:

**`web/app/mu-plugins/composer-compatibility-enforcer-rules.php`**

```php
<?php

add_filter('composer_compatibility_enforcer_rules', static function (array $rules): array {
    $rules[] = [
        'plugin_file' => 'google-listings-and-ads/google-listings-and-ads.php',
        'namespaces'  => ['Psr\\Log\\', 'Monolog\\'],
    ];

    return $rules;
});
```

> **Bedrock note:** Files directly in `mu-plugins/` are auto-loaded by WordPress. The rules file needs no loader entry — just drop it in.

### Rule Array Keys

Each rule array supports:

- `plugin_file` (required) — string; plugin main file path (relative to `WP_PLUGIN_DIR`)
- `namespaces` (optional) — array of namespace prefixes to remove from that plugin's autoloader
- `classes` (optional) — array of fully-qualified class names to block (sets classmap to `false`)

If both `namespaces` and `classes` are empty, the rule is skipped.

## Logging

Logs are written to the default PHP error log only when the
`composer_compatibility_enforcer_enable_logging` filter returns `true`.

## Known Offenders

Some plugins with known compatibility issues. PRs welcome!

### Google for WooCommerce | `wpackagist-plugin/google-listings-and-ads`

```php
$rules[] = [
    'plugin_file' => 'google-listings-and-ads/google-listings-and-ads.php',
    'namespaces' => ['Psr\\Log\\', 'Monolog\\'],
];
```

### Boekuwzending for Woocommerce | `wpackagist-plugin/boekuwzending-for-woocommerce`

```php
$rules[] = [
    'plugin_file' => 'boekuwzending-for-woocommerce/boekuwzending-for-woocommerce.php',
    'namespaces'  => ['Psr\\Log\\'],
];
```
