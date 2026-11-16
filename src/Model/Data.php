<?php

declare(strict_types=1);

namespace EnableBanking\Model;

use DateTimeImmutable;
use EnableBanking\Exception\UnexpectedResponseException;

/**
 * Helpers for reading decoded JSON payloads in a type-safe way.
 *
 * @internal
 */
final class Data
{
    /**
     * @param array<array-key, mixed> $data
     */
    public static function string(array $data, string $key): string
    {
        $value = self::nullableString($data, $key);
        if ($value === null) {
            throw new UnexpectedResponseException(sprintf('Missing required field "%s" in API response.', $key));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function nullableInt(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function nullableBool(array $data, string $key): ?bool
    {
        $value = $data[$key] ?? null;

        return is_bool($value) ? $value : null;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>|null
     */
    public static function nullableArray(array $data, string $key): ?array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? $value : null;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return list<array<array-key, mixed>>
     */
    public static function arrayList(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_array'));
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return list<string>
     */
    public static function stringList(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map('strval', array_filter($value, 'is_scalar')));
    }

    /**
     * Parses both date-time ("2020-12-01T12:00:00.000000+00:00") and date ("2020-01-03") values.
     *
     * @param array<array-key, mixed> $data
     */
    public static function nullableDate(array $data, string $key): ?DateTimeImmutable
    {
        $value = self::nullableString($data, $key);
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Exception $e) {
            throw new UnexpectedResponseException(sprintf('Invalid date "%s" in field "%s".', $value, $key), null, $e);
        }
    }
}
