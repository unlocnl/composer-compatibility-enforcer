<?php
declare(strict_types=1);

namespace Unloc\ComposerCompatibilityEnforcer;

use Composer\Autoload\ClassLoader;
use ReflectionObject;

final class Enforcer
{
    private array $rules;

    public function __construct(array $rules)
    {
        $this->rules = $rules;
    }

    public function apply(): void
    {
        foreach ($this->rules as $key => $rule) {
            if (!is_array($rule)) {
                continue;
            }

            if (!isset($rule['plugin_file']) && is_string($key)) {
                $rule['plugin_file'] = $key;
            }

            if (!isset($rule['plugin_file']) || !is_string($rule['plugin_file'])) {
                continue;
            }

            $pluginFile = trim($rule['plugin_file']);
            if ($pluginFile === '' || !$this->isPluginActive($pluginFile)) {
                continue;
            }

            $namespaces = $rule['namespaces'] ?? [];
            $classes = $rule['classes'] ?? [];
            if (!is_array($namespaces) || !is_array($classes)) {
                continue;
            }

            if (empty($namespaces) && empty($classes)) {
                continue;
            }

            $pluginPath = WP_PLUGIN_DIR . '/' . $pluginFile;
            $vendorDir = dirname($pluginPath) . '/vendor';

            if (!empty($namespaces)) {
                $this->blockNamespacesForVendor($vendorDir, $namespaces);
            }

            if (!empty($classes)) {
                $this->blockClassesForVendor($vendorDir, $classes);
            }
        }
    }

    private function isPluginActive(string $pluginFile): bool
    {
        if (!function_exists('get_option')) {
            return true;
        }

        $activePlugins = (array) get_option('active_plugins', []);
        if (in_array($pluginFile, $activePlugins, true)) {
            return true;
        }

        if (function_exists('is_multisite') && is_multisite()) {
            $activeSitewide = (array) get_site_option('active_sitewide_plugins', []);
            return isset($activeSitewide[$pluginFile]);
        }

        return false;
    }

    private function blockNamespacesForVendor(string $vendorDir, array $namespaces): void
    {
        if (!class_exists(ClassLoader::class)) {
            return;
        }

        $loaders = ClassLoader::getRegisteredLoaders();
        foreach ($loaders as $dir => $loader) {
            if (!str_starts_with($dir, $vendorDir)) {
                continue;
            }

            self::log(
                sprintf(
                    'Composer Compatibility Enforcer: stripping namespaces from loader %s: %s',
                    $dir,
                    implode(', ', $namespaces)
                )
            );

            foreach ($namespaces as $namespace) {
                $this->removeNamespaceFromLoader($loader, $namespace);
            }
        }
    }

    private function blockClassesForVendor(string $vendorDir, array $classes): void
    {
        if (!class_exists(ClassLoader::class)) {
            return;
        }

        $loaders = ClassLoader::getRegisteredLoaders();
        foreach ($loaders as $dir => $loader) {
            if (!str_starts_with($dir, $vendorDir)) {
                continue;
            }

            self::log(
                sprintf(
                    'Composer Compatibility Enforcer: blocking classes in loader %s: %s',
                    $dir,
                    implode(', ', $classes)
                )
            );

            $this->blockClassesInLoader($loader, $classes);
        }
    }

    private function removeNamespaceFromLoader(ClassLoader $loader, string $namespace): void
    {
        $loader->setPsr4($namespace, []);

        $ref = new ReflectionObject($loader);
        if (!$ref->hasProperty('classMap')) {
            return;
        }

        $prop = $ref->getProperty('classMap');
        $classMap = (array) $prop->getValue($loader);

        foreach ($classMap as $class => $path) {
            if (str_starts_with($class, $namespace)) {
                unset($classMap[$class]);
            }
        }

        $prop->setValue($loader, $classMap);
    }

    private function blockClassesInLoader(ClassLoader $loader, array $classes): void
    {
        $ref = new ReflectionObject($loader);
        if (!$ref->hasProperty('classMap')) {
            return;
        }

        $prop = $ref->getProperty('classMap');
        $classMap = (array) $prop->getValue($loader);

        // Setting a class to `false` in the classmap tells Composer's autoloader
        // that the class definitively does not exist in this loader, preventing
        // it from searching PSR-4 paths. This effectively blocks the class.
        foreach ($classes as $class) {
            $classMap[$class] = false;
        }

        $prop->setValue($loader, $classMap);
    }

    private static function log(string $message): void
    {
        $enabled = function_exists('apply_filters')
            && (bool) apply_filters('composer_compatibility_enforcer_enable_logging', false);

        if (!$enabled) {
            return;
        }

        $line = sprintf("[%s] %s\n", gmdate('Y-m-d H:i:s'), $message);
        error_log($line);
    }
}
