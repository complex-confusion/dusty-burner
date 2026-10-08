<?php

$tests_dir = getenv('WP_TESTS_DIR');

if (false === $tests_dir || '' === $tests_dir) {
    $tests_dir = dirname(__DIR__) . '/../wordpress-develop/tests/phpunit';
}

$tests_dir = realpath($tests_dir) ?: $tests_dir;
$functions_file = rtrim($tests_dir, DIRECTORY_SEPARATOR) . '/includes/functions.php';
$bootstrap_file = rtrim($tests_dir, DIRECTORY_SEPARATOR) . '/includes/bootstrap.php';

if (!is_file($functions_file) || !is_file($bootstrap_file)) {
    throw new RuntimeException(
        'WordPress PHPUnit test files were not found in '
        . $tests_dir
        . '. Set WP_TESTS_DIR to the tests/phpunit directory or place wordpress-develop beside this repository.'
    );
}

require_once $functions_file;

tests_add_filter('muplugins_loaded', static function() {
    require_once dirname(__DIR__) . '/lunacode-display-dust-data.php';
});

require $bootstrap_file;
