<?php
require __DIR__ . '/../line_rules.php';

function assert_equal($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, "Assertion failed: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

$rulesWithSpace = [['prefix' => 'T ', 'label' => 'Task', 'color' => '#123456']];
$sanitized = sanitize_line_rules($rulesWithSpace);
assert_equal('T ', $sanitized[0]['prefix'], 'sanitize_line_rules should preserve trailing whitespace');

$encoded = encode_line_rules_for_storage($rulesWithSpace);
$decoded = decode_line_rules_from_storage($encoded);
assert_equal('T ', $decoded[0]['prefix'], 'round trip through encode/decode should keep trailing whitespace');

echo "All PHP line rule tests passed\n";
