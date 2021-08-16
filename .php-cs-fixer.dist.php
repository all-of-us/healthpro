<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__ . '/symfony/src')
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
    ])
    ->setFinder($finder)
;
