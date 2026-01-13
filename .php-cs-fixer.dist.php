<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = new Finder()
    ->in(__DIR__);

return new Config()
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRules([
        '@PhpCsFixer' => true,
        'multiline_whitespace_before_semicolons' => ['strategy' => 'no_multi_line'],
        'yoda_style' => ['always_move_variable' => true, 'identical' => false],
        'braces_position' => [
            'classes_opening_brace' => 'same_line',
            'control_structures_opening_brace' => 'same_line',
            'functions_opening_brace' => 'same_line',
        ],
        '@PHP84Migration' => true,
        '@PhpCsFixer:risky' => true,
        'php_unit_strict' => false,
        '@PHP82Migration:risky' => true,
        'class_definition' => ['single_item_single_line' => true],
        'global_namespace_import' => ['import_classes' => true, 'import_constants' => true, 'import_functions' => true],
        'php_unit_test_class_requires_covers' => false,
        'php_unit_test_case_static_method_calls' => false,
    ])
    ->setFinder($finder)
    ->setUsingCache(false)
    ->setRiskyAllowed(true)
    ->setIndent(str_repeat(' ', 4))
    ->setLineEnding("\n");
