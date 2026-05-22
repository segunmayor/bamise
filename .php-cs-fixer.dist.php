<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS2.0'                         => true,
        '@PHP84Migration'                    => true,

        // Strict types
        'declare_strict_types'               => true,
        'strict_param'                       => true,
        'strict_comparison'                  => true,

        // Imports
        'ordered_imports'                    => ['sort_algorithm' => 'alpha'],
        'no_unused_imports'                  => true,
        'single_import_per_statement'        => true,
        'global_namespace_import'            => [
            'import_classes'   => false,
            'import_constants' => false,
            'import_functions' => false,
        ],

        // Arrays / trailing commas
        'trailing_comma_in_multiline'        => ['elements' => ['arrays', 'arguments', 'parameters']],
        'array_syntax'                       => ['syntax' => 'short'],

        // Spacing / alignment
        'binary_operator_spaces'             => true,
        'concat_space'                       => ['spacing' => 'one'],
        'object_operator_without_whitespace' => true,
        'not_operator_with_successor_space'  => false,

        // Casts
        'cast_spaces'                        => ['space' => 'single'],
        'lowercase_cast'                     => true,
        'short_scalar_cast'                  => true,

        // Misc
        'single_quote'                       => true,
        'no_empty_comment'                   => true,
        'no_superfluous_phpdoc_tags'         => ['allow_mixed' => true],
        'phpdoc_align'                       => ['align' => 'left'],
        'phpdoc_order'                       => true,
    ])
    ->setFinder($finder);
