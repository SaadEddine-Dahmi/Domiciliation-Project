<?php

namespace App\Support;

class DatabaseErrorHelper
{
    private const UNDEFINED_COLUMN_CODES = [
        '42S22', // MySQL / MariaDB
        '42703', // PostgreSQL
    ];

    public static function isUndefinedColumnError(\Throwable $e): bool
    {
        if ($e instanceof \PDOException && isset($e->errorInfo[0])) {
            if (in_array((string) $e->errorInfo[0], self::UNDEFINED_COLUMN_CODES, true)) {
                return true;
            }
        }

        $code = (string) $e->getCode();
        if (in_array($code, self::UNDEFINED_COLUMN_CODES, true)) {
            return true;
        }

        $message = $e->getMessage();

        return str_contains($message, 'Unknown column')
            || str_contains($message, 'does not exist');
    }
}