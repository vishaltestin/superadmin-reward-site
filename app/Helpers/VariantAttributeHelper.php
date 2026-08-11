<?php

namespace App\Helpers;

use Illuminate\Support\Str;

class VariantAttributeHelper
{
    /**
     * Common attribute names offered as suggestions in the admin form.
     */
    public const COMMON_KEYS = [
        'Size',
        'Color',
        'Material',
        'Style',
        'Weight',
        'Capacity',
        'Fabric',
        'Pattern',
    ];

    /**
     * Normalize an attribute key so that "size", "Size" and "SIZE"
     * are all treated as the exact same attribute ("Size").
     *
     * Rules: trim, collapse inner whitespace, canonical Title Case.
     */
    public static function normalizeKey(int | string | null $key): ?string
    {
        if ($key === null) {
            return null;
        }

        $key = trim((string) preg_replace('/\s+/u', ' ', (string) $key));

        if ($key === '') {
            return null;
        }

        return Str::title(mb_strtolower($key));
    }

    /**
     * Whether an attribute key represents a color
     * (e.g. "Color", "color", "Frame Color").
     */
    public static function isColorKey(int | string | null $key): bool
    {
        $key = static::normalizeKey($key);

        if ($key === null) {
            return false;
        }

        return in_array($key, ['Color', 'Colour'], true)
            || Str::endsWith($key, [' Color', ' Colour']);
    }

    /**
     * Normalize a full attributes map ({"size": "XL", "Color": "#fff"}):
     * - keys are trimmed + Title Cased (case-insensitive duplicates merge, last value wins)
     * - values are trimmed
     * - rows with empty keys or empty values are dropped
     */
    public static function normalizeMap(mixed $attributes): array
    {
        if (! is_array($attributes)) {
            return [];
        }

        $normalized = [];

        foreach ($attributes as $key => $value) {
            $normalizedKey = static::normalizeKey($key);

            if ($normalizedKey === null || is_array($value)) {
                continue;
            }

            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === null || $value === '') {
                continue;
            }

            // Normalized keys are unique, so duplicates merge automatically (last wins).
            $normalized[$normalizedKey] = $value;
        }

        return $normalized;
    }
}
