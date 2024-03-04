<?php

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests'])
;

$config = new PhpCsFixer\Config();
$config->setRiskyAllowed(true);

return $config->setRules([
    '@PER-CS2.0' => true,
    'strict_param' => true,
    'no_unused_imports' => true,
    'trailing_comma_in_multiline' => true,
    'array_syntax' => ['syntax' => 'short'],
    'method_argument_space' => false,
])->setFinder($finder);