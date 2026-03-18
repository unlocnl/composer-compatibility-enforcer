<?php
/**
 * Plugin Name: Composer Compatibility Enforcer
 * Description: Removes selected namespaces/classes from plugin Composer autoloaders.
 * Plugin URI:  https://github.com/unlocnl/composer-compatibility-enforcer
 * Version:     1.0.0
 * Author:      David Beentjes
 * Author URI:  https://unloc.nl
 */

if (!defined('ABSPATH')) {
    return;
}

if (!class_exists(\Unloc\ComposerCompatibilityEnforcer\Enforcer::class)) {
    require_once __DIR__ . '/src/Enforcer.php';
}

add_action(
    'plugins_loaded',
    static function (): void {
        $rules = apply_filters('composer_compatibility_enforcer_rules', []);
        if (empty($rules)) {
            return;
        }

        $enforcer = new \Unloc\ComposerCompatibilityEnforcer\Enforcer((array)$rules);
        $enforcer->apply();
    },
    11
);
