<?php

$tests_dir = getenv('WP_TESTS_DIR');

if (false === $tests_dir || '' === $tests_dir) {
    throw new RuntimeException('Set WP_TESTS_DIR to the WordPress PHPUnit test-library directory.');
}

$functions_file = rtrim($tests_dir, DIRECTORY_SEPARATOR) . '/includes/functions.php';
$bootstrap_file = rtrim($tests_dir, DIRECTORY_SEPARATOR) . '/includes/bootstrap.php';

if (!is_file($functions_file) || !is_file($bootstrap_file)) {
    throw new RuntimeException(
        'WP_TESTS_DIR must contain includes/functions.php and includes/bootstrap.php: ' . $tests_dir
    );
}

require_once $functions_file;

tests_add_filter('muplugins_loaded', static function() {
    require_once dirname(__DIR__) . '/lunacode-display-dust-data.php';
});

require $bootstrap_file;
