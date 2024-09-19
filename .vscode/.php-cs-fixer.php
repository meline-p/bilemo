<?php

// https://cs.symfony.com/doc/rules/
$config = new PhpCsFixer\Config();
$config
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        'no_alternative_syntax' => ['fix_non_monolithic_code' => false],
        'echo_tag_syntax' => ['format' => 'short'],
        'global_namespace_import' => ['import_classes' => false, 'import_constants' => false, 'import_functions' => false],
    ])
;

return $config;
