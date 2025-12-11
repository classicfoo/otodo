<?php

function sanitize_text_expanders($input) {
    if (!is_array($input)) {
        return [];
    }

    $normalized = [];
    foreach ($input as $item) {
        if (!is_array($item)) {
            continue;
        }
        $shortcut = trim((string)($item['shortcut'] ?? ''));
        $expansion = trim((string)($item['expansion'] ?? ''));
        if ($shortcut === '' || $expansion === '') {
            continue;
        }
        $normalized[] = [
            'shortcut' => $shortcut,
            'expansion' => $expansion,
        ];
    }

    return $normalized;
}

function encode_text_expanders_for_storage($expanders) {
    return json_encode(sanitize_text_expanders($expanders));
}

function decode_text_expanders_from_storage($value) {
    if (!is_string($value) || $value === '') {
        return [];
    }
    $decoded = json_decode($value, true);
    return sanitize_text_expanders($decoded);
}
