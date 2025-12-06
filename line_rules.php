<?php

function get_default_line_rules() {
    return [
        ['prefix' => 'T ', 'label' => 'Task', 'color' => '#1D4ED8', 'className' => 'code-line-task'],
        ['prefix' => 'N ', 'label' => 'Note', 'color' => '#1E7A3E', 'className' => 'code-line-note'],
        ['prefix' => 'M ', 'label' => 'Milestone', 'color' => '#800000', 'className' => 'code-line-milestone'],
        ['prefix' => '# ', 'label' => 'Heading', 'color' => '#212529', 'weight' => '700', 'className' => 'code-line-heading'],
        ['prefix' => 'X ', 'label' => 'Done', 'color' => '#6C757D', 'className' => 'code-line-done'],
    ];
}

function normalize_editor_color($color) {
    $trimmed = strtoupper(trim($color ?? ''));
    if (!preg_match('/^#[0-9A-F]{6}$/', $trimmed)) {
        return '#212529';
    }
    return $trimmed;
}

function sanitize_line_rules($rules) {
    if (!is_array($rules)) {
        return [];
    }

    $cleaned = [];
    foreach ($rules as $rule) {
        if (!is_array($rule)) {
            continue;
        }
        $rawPrefix = isset($rule['prefix']) ? (string)$rule['prefix'] : '';
        if (trim($rawPrefix) === '') {
            continue;
        }
        $prefix = $rawPrefix;
        $color = isset($rule['color']) ? strtoupper(trim((string)$rule['color'])) : null;
        if ($color && !preg_match('/^#[0-9A-F]{6}$/', $color)) {
            $color = null;
        }
        $label = isset($rule['label']) ? trim((string)$rule['label']) : '';
        $weight = isset($rule['weight']) && in_array((string)$rule['weight'], ['400', '700'], true) ? (string)$rule['weight'] : null;

        $className = isset($rule['className']) ? trim((string)$rule['className']) : '';
        if ($className !== '' && !preg_match('/^[A-Za-z0-9_-]+$/', $className)) {
            $className = '';
        }

        $cleaned[] = array_filter([
            'prefix' => $prefix,
            'label' => $label !== '' ? $label : null,
            'color' => $color,
            'weight' => $weight,
            'className' => $className !== '' ? $className : null,
        ], function ($value) {
            return $value !== null && $value !== '';
        });
    }

    return array_slice($cleaned, 0, 25);
}

function encode_line_rules_for_storage($rules) {
    $sanitized = sanitize_line_rules($rules);
    return json_encode($sanitized, JSON_UNESCAPED_SLASHES);
}

function decode_line_rules_from_storage($raw) {
    $decoded = json_decode($raw ?? '', true);
    $sanitized = sanitize_line_rules($decoded);
    if (empty($sanitized)) {
        $sanitized = get_default_line_rules();
    }
    return $sanitized;
}

