<?php

namespace App\Database;

use Illuminate\Database\MySqlConnection;

class MemoizedMySqlConnection extends MySqlConnection
{
    public function getSchemaBuilder()
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new MemoizedMySqlBuilder($this);
    }
}
