<?php

date_default_timezone_set('UTC');

$getEnvVar = function ($name, $default = false) {
    if (false !== $value = getenv($name)) {
        return $value;
    }

    static $phpunitConfig = null;
    if (null === $phpunitConfig) {
        $phpunitConfigFilename = null;
        if (file_exists('phpunit.xml')) {
            $phpunitConfigFilename = 'phpunit.xml';
        } elseif (file_exists('phpunit.xml.dist')) {
            $phpunitConfigFilename = 'phpunit.xml.dist';
        }
        if ($phpunitConfigFilename) {
            $phpunitConfig = new DomDocument();
            $phpunitConfig->load($phpunitConfigFilename);
        } else {
            $phpunitConfig = false;
        }
    }
    if (false !== $phpunitConfig) {
        $var = new DOMXpath($phpunitConfig);
        foreach ($var->query('//php/env[@name="' . $name . '"]') as $var) {
            return $var->getAttribute('value');
        }
    }

    return $default;
};
$root = __DIR__ . '/../../..';
$PHPUNIT_VERSION = $getEnvVar('SYMFONY_PHPUNIT_VERSION', '6.5');
$PHPUNIT_DIR = $getEnvVar('SYMFONY_PHPUNIT_DIR', $root . '/vendor/bin/.phpunit');

require __DIR__ . '/../../../vendor/autoload.php';
// For behat tests to use php assertion
$phpUnitAutoload = "$PHPUNIT_DIR/phpunit-$PHPUNIT_VERSION/vendor/autoload.php";
if (file_exists($phpUnitAutoload)) {
    require $phpUnitAutoload;
}

require __DIR__ . '/AppKernel.php';
