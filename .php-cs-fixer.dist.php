<?php

$finder = PhpCsFixer\Finder::create()
    ->exclude('vendor')
    ->exclude('src')
    ->in(__DIR__)
;

$config = new PhpCsFixer\Config();

return $config->setRules([
    '@PHP81Migration' => true,
    '@PhpCsFixer' => true,
    'array_syntax' => ['syntax' => 'short'],
    'no_useless_else' => false,
    'curly_braces_position' => [
        'functions_opening_brace' => 'same_line',
        'classes_opening_brace' => 'same_line',
    ],
    'constant_case' => [
        'case' => 'upper',
    ],
    'concat_space' => [
        'spacing' => 'one',
    ],
    'no_superfluous_phpdoc_tags' => false,
    'method_argument_space' => [
        'on_multiline' => 'ensure_single_line',
    ],
])
    ->setFinder($finder)
;