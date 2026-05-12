<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = new Finder()
  ->in([
    __DIR__ . '/src',
    __DIR__ . '/tests',
    __DIR__ . '/tools/generator/src',
  ]);

return new Config()
  ->setRules([
    // PER Coding Style v2.0 (https://github.com/php-fig/per-coding-style/blob/2.0.0/spec.md)
    '@PER-CS2.0' => true,
    // Each element of an array must be indented exactly once.
    'array_indentation' => true,
    // PHP arrays should be declared using the configured syntax.
    'array_syntax' => true,
    // PHP attributes declared without arguments must (not) be followed by empty parentheses.
    'attribute_empty_parentheses' => true,
    // An empty line feed must precede any configured statement.
    'blank_line_before_statement' => [
      'statements' => [
        'break',
        'case',
        'continue',
        'declare',
        'default',
        'exit',
        'for',
        'foreach',
        'goto',
        'if',
        'phpdoc',
        'return',
        'switch',
        'throw',
        'try',
        'while',
        'yield',
        'yield_from',
      ],
    ],
    // A single space or none should be between cast and variable.
    'cast_spaces' => ['space' => 'none'],
    // When referencing an internal class it must be written using the correct casing.
    'class_reference_name_casing' => true,
    // Namespace must not contain spacing, comments or PHPDoc.
    'clean_namespace' => true,
    // Using `isset($var) &&` multiple times should be done in one call.
    'combine_consecutive_issets' => true,
    // Calling `unset` on multiple items should be done in one call.
    'combine_consecutive_unsets' => true,
    // Replace multiple nested calls of `dirname` by only one call with second `$level` parameter. Requires PHP >= 7.0.
    'combine_nested_dirname' => true,
    // There must not be spaces around `declare` statement parentheses.
    'declare_parentheses' => true,
    // Replaces `dirname(__FILE__)` expression with equivalent `__DIR__` constant.
    'dir_constant' => true,
    // Replaces short-echo `<?=` with long format `<?php echo`/`<?php print` syntax, or vice-versa.
    'echo_tag_syntax' => [
      'format' => 'short',
      'shorten_simple_statements_only' => true,
    ],
    // Empty loop-body must be in configured style.
    'empty_loop_body' => ['style' => 'braces'],
    // Empty loop-condition must be in configured style.
    'empty_loop_condition' => true,
    // Converts implicit variables into explicit ones in double-quoted strings or heredoc syntax.
    'explicit_string_variable' => true,
    // Order the flags in `fopen` calls, `b` and `t` must be last.
    'fopen_flag_order' => true,
    // Removes the leading part of fully qualified symbol references if a given symbol is imported or belongs to the current namespace.
    'fully_qualified_strict_types' => [
      'import_symbols' => true,
    ],
    // Renames PHPDoc tags.
    'general_phpdoc_tag_rename' => [
      'case_sensitive' => true,
      'replacements' => [
        'inheritdoc' => 'inheritDoc',
        'inheritdocs' => 'inheritDoc',
        'inheritDocs' => 'inheritDoc',
      ],
    ],
    // Replace `get_class` calls on object variables with class keyword syntax.
    'get_class_to_class_keyword' => true,
    // Imports or fully qualifies global classes/functions/constants.
    'global_namespace_import' => true,
    // Function `implode` must be called with 2 arguments in the documented order.
    'implode_call' => true,
    // Include/Require and file path should be divided with a single space. File path should not be placed within parentheses.
    'include' => true,
    // Integer literals must be in correct case.
    'integer_literal_case' => true,
    // Replaces `is_null($var)` expression with `null === $var`.
    'is_null' => true,
    // Lambda must not import variables it doesn't use.
    'lambda_not_used_import' => true,
    // List (`array` destructuring) assignment should be declared using the configured syntax. Requires PHP >= 7.1.
    'list_syntax' => true,
    // Magic constants should be referred to using the correct casing.
    'magic_constant_casing' => true,
    // Magic method definitions and calls must be using the correct casing.
    'magic_method_casing' => true,
    // Method chaining MUST be properly indented. Method chaining with different levels of indentation is not supported.
    'method_chaining_indentation' => true,
    // Replace `strpos()` calls with `str_starts_with()` or `str_contains()` if possible.
    'modernize_strpos' => true,
    // Replaces `intval`, `floatval`, `doubleval`, `strval` and `boolval` function calls with according type casting operator.
    'modernize_types_casting' => true,
    // DocBlocks must start with two asterisks, multiline comments must start with a single asterisk, after the opening slash. Both must end with a single asterisk before the closing slash.
    'multiline_comment_opening_closing' => true,
    // Forbid multi-line whitespace before the closing semicolon or move the semicolon to the new line for chained calls.
    'multiline_whitespace_before_semicolons' => true,
    // Function defined by PHP should be called using the correct casing.
    'native_function_casing' => true,
    // Native type declarations should be used in the correct case.
    'native_type_declaration_casing' => true,
    // Master functions shall be used instead of aliases.
    'no_alias_functions' => ['sets' => ['@internal']],
    // Replace control structure alternative syntax to use braces.
    'no_alternative_syntax' => ['fix_non_monolithic_code' => false],
    // There should not be blank lines between docblock and the documented element.
    'no_blank_lines_after_phpdoc' => true,
    // There should not be any empty comments.
    'no_empty_comment' => true,
    // There should not be empty PHPDoc blocks.
    'no_empty_phpdoc' => true,
    // Remove useless (semicolon) statements.
    'no_empty_statement' => true,
    // Removes extra blank lines and/or blank lines following configuration.
    'no_extra_blank_lines' => [
      'tokens' => [
        'case',
        'continue',
        'curly_brace_block',
        'default',
        'extra',
        'parenthesis_brace_block',
        'return',
        'square_brace_block',
        'switch',
        'throw',
        'use',
      ],
    ],
    // Replace accidental usage of homoglyphs (non ascii characters) in names.
    'no_homoglyph_names' => true,
    // The namespace declaration line shouldn't contain leading whitespace.
    'no_leading_namespace_whitespace' => true,
    // Either language construct `print` or `echo` should be used.
    'no_mixed_echo_print' => true,
    // Operator `=>` should not be surrounded by multi-line whitespaces.
    'no_multiline_whitespace_around_double_arrow' => true,
    // Convert PHP4-style constructors to `__construct`.
    'no_php4_constructor' => true,
    // Short cast `bool` using double exclamation mark should not be used.
    'no_short_bool_cast' => true,
    // Single-line whitespace before closing semicolon are prohibited.
    'no_singleline_whitespace_before_semicolons' => true,
    // There MUST NOT be spaces around offset braces.
    'no_spaces_around_offset' => true,
    // Replaces superfluous `elseif` with `if`.
    'no_superfluous_elseif' => true,
    // If a list of values separated by a comma is contained on a single line, then the last item MUST NOT have a trailing comma.
    'no_trailing_comma_in_singleline' => true,
    // Removes unneeded parentheses around control statements.
    'no_unneeded_control_parentheses' => [
      'statements' => [
        'break',
        'clone',
        'continue',
        'echo_print',
        'negative_instanceof',
        'others',
        'return',
        'switch_case',
        'yield',
        'yield_from',
      ],
    ],
    // Imports should not be aliased as the same name.
    'no_unneeded_import_alias' => true,
    // In function arguments there must not be arguments with default values before non-default ones.
    'no_unreachable_default_argument_value' => true,
    // Variables must be set `null` instead of using `(unset)` casting.
    'no_unset_cast' => true,
    // Unused `use` statements must be removed.
    'no_unused_imports' => true,
    // There must be no `sprintf` calls with only the first argument.
    'no_useless_sprintf' => true,
    // In array declaration, there MUST NOT be a whitespace before each comma.
    'no_whitespace_before_comma_in_array' => ['after_heredoc' => true],
    // Array index should always be written by using square braces.
    'normalize_index_brace' => true,
    // Nullable single type declaration should be standardised using configured syntax.
    'nullable_type_declaration' => true,
    // Adds or removes `?` before single type declarations or `|null` at the end of union types when parameters have a default `null` value.
    'nullable_type_declaration_for_default_null_value' => true,
    // There should not be space before or after object operators `->` and `?->`.
    'object_operator_without_whitespace' => true,
    // Operators - when multiline - must always be at the beginning or at the end of the line.
    'operator_linebreak' => true,
    // Sorts attributes using the configured sort algorithm.
    'ordered_attributes' => true,
    // Ordering `use` statements.
    'ordered_imports' => [
      'sort_algorithm' => 'alpha',
    ],
    // Sort union types and intersection types using configured order.
    'ordered_types' => true,
    // All items of the given PHPDoc tags must be either left-aligned or (by default) aligned vertically.
    'phpdoc_align' => ['align' => 'left'],
    // Docblocks should have the same indentation as the documented subject.
    'phpdoc_indent' => true,
    // Fixes PHPDoc inline tags.
    'phpdoc_inline_tag_normalizer' => true,
    // `@access` annotations should be omitted from PHPDoc.
    'phpdoc_no_access' => true,
    // No alias PHPDoc tags should be used.
    'phpdoc_no_alias_tag' => true,
    // Classy that does not inherit must not have `@inheritdoc` tags.
    'phpdoc_no_useless_inheritdoc' => true,
    // Annotations in PHPDoc should be ordered in defined sequence.
    'phpdoc_order' => [
      'order' => [
        'param',
        'return',
        'throws',
      ],
    ],
    // Order PHPDoc tags by value.
    'phpdoc_order_by_value' => true,
    // Orders all `@param` annotations in DocBlocks according to method signature.
    'phpdoc_param_order' => true,
    // The type of `@return` annotations of methods returning a reference to itself must the configured one.
    'phpdoc_return_self_reference' => true,
    // Scalar types should always be written in the same form. `int` not `integer`, `bool` not `boolean`, `float` not `real` or `double`.
    'phpdoc_scalar' => true,
    // Annotations in PHPDoc should be grouped together so that annotations of the same type immediately follow each other. Annotations of a different type are separated by a single blank line.
    'phpdoc_separation' => true,
    // Single line `@var` PHPDoc should have proper spacing.
    'phpdoc_single_line_var_spacing' => true,
    // Fixes casing of PHPDoc tags.
    'phpdoc_tag_casing' => true,
    // Docblocks should only be used on structural elements.
    'phpdoc_to_comment' => ['ignored_tags' => ['todo', 'global', 'var']],
    // PHPDoc should start and end with content, excluding the very first and last line of the docblocks.
    'phpdoc_trim' => true,
    // Removes extra blank lines after summary and after description in PHPDoc.
    'phpdoc_trim_consecutive_blank_line_separation' => true,
    // The correct case must be used for standard PHP types in PHPDoc.
    'phpdoc_types' => true,
    // Sorts PHPDoc types.
    'phpdoc_types_order' => true,
    // `@var` and `@type` annotations must have type and name in the correct order.
    'phpdoc_var_annotation_correct_order' => true,
    // `@var` and `@type` annotations of classy properties should not contain the name.
    'phpdoc_var_without_name' => true,
    // If the function explicitly returns an array, and has the return type `iterable`, then `yield from` must be used instead of `return`.
    'return_to_yield_from' => true,
    // Inside an enum or `final`/anonymous class, `self` should be preferred over `static`.
    'self_static_accessor' => true,
    // Instructions must be terminated with a semicolon.
    'semicolon_after_instruction' => true,
    // Converts explicit variables in double-quoted strings and heredoc syntax from simple to complex format (`${` to `{$`).
    'simple_to_complex_string_variable' => true,
    // Simplify `if` control structures that return the boolean result of their condition.
    'simplified_if_return' => true,
    // A return statement wishing to return `void` should not return `null`.
    'simplified_null_return' => true,
    // Single-line comments must have proper spacing.
    'single_line_comment_spacing' => true,
    // Single-line comments and multi-line comments with only one line of actual content should use the `//` syntax.
    'single_line_comment_style' => true,
    // Convert double quotes to single quotes for simple strings.
    'single_quote' => true,
    // Ensures a single space after language constructs.
    'single_space_around_construct' => true,
    // Fix whitespace after a semicolon.
    'space_after_semicolon' => true,
    // Replace all `<>` with `!=`.
    'standardize_not_equals' => true,
    // Lambdas not (indirectly) referencing `$this` must be declared `static`.
    'static_lambda' => true,
    // Handles implicit backslashes in strings and heredocs. Depending on the chosen strategy, it can escape implicit backslashes to ease the understanding of which are special chars interpreted by PHP and which not (`escape`), or it can remove these additional backslashes if you find them superfluous (`unescape`). You can also leave them as-is using `ignore` strategy.
    'string_implicit_backslashes' => ['single_quoted' => 'ignore'],
    // Switch case must not be ended with `continue` but with `break`.
    'switch_continue_to_break' => true,
    // Use `null` coalescing operator `??` where possible. Requires PHP >= 7.0.
    'ternary_to_null_coalescing' => true,
    // Multi-line arrays, arguments list, parameters list and `match` expressions must have a trailing comma.
    'trailing_comma_in_multiline' => ['after_heredoc' => true],
    // Arrays should be formatted like function/method arguments, without leading or trailing single line space.
    'trim_array_spaces' => true,
    // Ensure single space between a variable and its type declaration in function arguments and properties.
    'type_declaration_spaces' => true,
    // A single space or none should be around union type and intersection type operators.
    'types_spaces' => true,
    // Unary operators should be placed adjacent to their operands.
    'unary_operator_spaces' => ['only_dec_inc' => false],
    // In array declaration, there MUST be a whitespace after each comma.
    'whitespace_after_comma_in_array' => ['ensure_single_space' => true],
    // Write conditions in Yoda style (`true`), non-Yoda style (`['equal' => false, 'identical' => false, 'less_and_greater' => false]`) or ignore those conditions (`null`) based on configuration.
    'yoda_style' => [
      'equal' => false,
      'identical' => false,
      'less_and_greater' => false,
    ],
  ])
  ->setRiskyAllowed(true)
  ->setIndent('  ')
  ->setLineEnding("\n")
  ->setParallelConfig(ParallelConfigFactory::detect())
  ->setFinder($finder);
