<?php
declare(strict_types=1);

use Isolated\Symfony\Component\Finder\Finder;
//use Symfony\Component\Finder\Finder;

// Obtain original namespace (must be the first entry in autoload.psr-4)
$composerJson = json_decode(file_get_contents('composer.json'), true);
$psr4 = array_keys($composerJson['autoload']['psr-4'])[0];

// Obtain stubs generated by grunt task "php:scope"
$stubsJson = json_decode(file_get_contents('php-scoper.php.json'), true);

// Whitelist all available monorepo-plugins
$whiteListPlugins = glob('../../../*', GLOB_ONLYDIR);
foreach ($whiteListPlugins as $key => $plugin) {
    $composerJson = json_decode(file_get_contents($plugin . '/composer.json'), true);
    $whiteListPlugins[$key] = array_keys($composerJson['autoload']['psr-4'])[0] . '*';
}

$apiFolder = getcwd() . '/inc/api/';

return [
    'prefix' => $psr4 . 'Vendor',
    'finders' => [
        Finder::create()
            ->files()
            ->in('inc'),
        Finder::create()
            ->files()
            ->notName('/LICENSE|.*\\.md|.*\\.dist|Makefile|composer\\.json|composer\\.lock/')
            ->exclude(['doc', 'test', 'test_old', 'tests', 'Tests', 'vendor-bin'])
            ->in('vendor'),
        Finder::create()->append(['composer.json'])
    ],
    'patchers' => [
        /**
         * Allow to remove namespace for a file, when the defined functions should be globally available.
         * This is useful for plugin APIs.
         */
        function ($filePath, $prefix, $content) use ($apiFolder) {
            if (strpos($filePath, $apiFolder, 0) === 0) {
                $prefixDoubleSlashed = str_replace('\\', '\\\\', $prefix);
                return preg_replace(sprintf('/^namespace %s;$/m', $prefixDoubleSlashed), '', $content, 1);
            }
            return $content;
        },
        /**
         * This callback removes un-prefix all classes and functions obtained from stubs because
         * they are available globally in our WP ecosystem.
         */
        function ($filePath, $prefix, $content) use ($stubsJson) {
            $prefixDoubleSlashed = str_replace('\\', '\\\\', $prefix);
            $quotes = ['\'', '"', '`'];

            foreach ($stubsJson as $identifier) {
                $identifierDoubleSlashed = str_replace('\\', '\\\\', $identifier);
                $content = str_replace($prefix . '\\' . $identifier, $identifier, $content); // "PREFIX\foo()", or "foo extends nativeClass"

                // Replace in strings, e. g.  "if( function_exists('PREFIX\\foo') )"
                foreach ($quotes as $quote) {
                    $content = str_replace(
                        $quote . $prefixDoubleSlashed . '\\\\' . $identifierDoubleSlashed . $quote,
                        $quote . $identifierDoubleSlashed . $quote,
                        $content
                    );
                }
            }
            return $content;
        }
    ],
    'whitelist' => $whiteListPlugins,
    'files-whitelist' => array_merge(
        array_map(
            'realpath',
            array_keys(
                iterator_to_array(
                    Finder::create()
                        ->files()
                        ->in('inc/base/others')
                        ->notName('start.php')
                )
            )
        )
    ),
    'whitelist-global-constants' => true,
    'whitelist-global-classes' => false,
    'whitelist-global-functions' => false
];
