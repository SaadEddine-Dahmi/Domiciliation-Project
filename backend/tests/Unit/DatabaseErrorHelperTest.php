<?php
// tests/Unit/DatabaseErrorHelperTest.php

namespace Tests\Unit;

use App\Support\DatabaseErrorHelper;
use Tests\TestCase;

class DatabaseErrorHelperTest extends TestCase
{
    /** @test */
    public function detects_mysql_undefined_column_by_sqlstate_code(): void
    {
        $e = new \PDOException('SQLSTATE[42S22]: Column not found: 1054 Unknown column', 1054);
        $e->errorInfo = ['42S22', 1054, 'Unknown column'];

        $this->assertTrue(DatabaseErrorHelper::isUndefinedColumnError($e));
    }

    /** @test */
    public function detects_postgres_undefined_column_by_sqlstate_code(): void
    {
        $e = new \PDOException('ERROR: column "x" does not exist', 0);
        $e->errorInfo = ['42703', 0, 'column "x" does not exist'];

        $this->assertTrue(DatabaseErrorHelper::isUndefinedColumnError($e));
    }

    /** @test */
    public function falls_back_to_message_matching_when_code_is_unrecognised(): void
    {
        $e = new \Exception('column "notification_preferences" does not exist', 0);
        $this->assertTrue(DatabaseErrorHelper::isUndefinedColumnError($e));
    }

    /** @test */
    public function unrelated_database_errors_are_not_treated_as_undefined_column(): void
    {
        $e = new \Exception('SQLSTATE[23505]: Unique violation', 23505);
        $this->assertFalse(DatabaseErrorHelper::isUndefinedColumnError($e));
    }
}