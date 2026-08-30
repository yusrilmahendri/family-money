<?php

namespace App\Support;

final class CanonicalJson
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function encode(array $data): string
    {
        return json_encode(self::sort($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function hash(array $data): string
    {
        return hash('sha256', self::encode($data));
    }

    /**
     * @param  mixed  $value
     * @return mixed
     */
    public static function sort(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn ($item) => self::sort($item), $value);
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = self::sort($item);
        }

        return $value;
    }
}
