<?php

use Symfony\Component\Yaml\Parser;

// Determine migration target from loaded environment variable, set at runtime
$migrationTarget = isset($_ENV['MIGRATION_TARGET']) ? $_ENV['MIGRATION_TARGET'] : null;
if (!$migrationTarget) {
    throw new \Exception('Must prefix command with `MIGRATION_TARGET=<environment>`, see ' . __FILE__);
}

$filename = basename(sprintf('migrations-%s.yml', $migrationTarget));
if (file_exists(dirname(__FIlE__) . '/' .$filename)) {
    $yaml = new Parser();
    return $yaml->parse(file_get_contents(dirname(__FIlE__) . '/' . $filename));
}

// If file not found, show additional help instructions
throw new Exception(
    sprintf(
        'To run this migration, you will want a file called `%s` in your `./dev_config` folder. See migrations-staging.yml.dist for an exmaple.',
        $filename
    )
);
