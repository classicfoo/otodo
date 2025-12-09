<?php

function get_default_date_formats(): array {
    return ['DD MMM YYYY'];
}

function sanitize_date_formats_input($input): array {
    if (is_string($input)) {
        $lines = preg_split('/\r\n|\r|\n/', $input);
    } elseif (is_array($input)) {
        $lines = $input;
    } else {
        $lines = [];
    }

    $cleaned = [];
    foreach ($lines as $line) {
        $format = trim((string)$line);
        if ($format === '') {
            continue;
        }

        if (!preg_match('/(DD|D|MMMM|MMM|MM|M|YYYY|YY)/', $format)) {
            continue;
        }

        $cleaned[] = mb_substr($format, 0, 60);
    }

    $unique = array_values(array_unique($cleaned));
    if (empty($unique)) {
        return get_default_date_formats();
    }
    return $unique;
}

function encode_date_formats_for_storage(array $formats): string {
    return json_encode(array_values($formats));
}

function decode_date_formats_from_storage(?string $value): array {
    if (!$value) {
        return get_default_date_formats();
    }

    $decoded = json_decode($value, true);
    if (!is_array($decoded)) {
        return get_default_date_formats();
    }

    return sanitize_date_formats_input($decoded);
}

