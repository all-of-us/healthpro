<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__ . '/src');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'align_multiline_comment' => ['comment_type' => 'all_multiline'],
        'binary_operator_spaces' => true,
        'braces' => [
            'allow_single_line_closure' => true,
            'position_after_functions_and_oop_constructs' => 'next',
            'position_after_control_structures' => 'same',
            'position_after_anonymous_constructs' => 'same'
        ],
        'array_syntax' => ['syntax' => 'short'],
        'cast_spaces' => ['space' =>'single'],
        'concat_space' => ['spacing' => 'one'],
        'function_typehint_space' => true,
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
        ],
        'native_function_casing' => true,
        'no_blank_lines_after_phpdoc' => true,
        'no_extra_blank_lines' => false,
        'no_empty_comment' => true,
        'no_empty_phpdoc' => true,
        'no_empty_statement' => true,
        'no_leading_namespace_whitespace' => true,
        'no_singleline_whitespace_before_semicolons' => true,
        'no_trailing_whitespace' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'normalize_index_brace' => true,
        'ordered_class_elements' => true,
        'ordered_imports' => true,
        'phpdoc_order' => true,
        'protected_to_private' => true,
        'single_import_per_statement' => false,
        'single_line_comment_style' => true,
        'single_quote' => true,
        'space_after_semicolon' => true,
        'unary_operator_spaces' => true,
        'whitespace_after_comma_in_array' => true,
        'no_unused_imports' => true
    ])
    ->setFinder($finder);
