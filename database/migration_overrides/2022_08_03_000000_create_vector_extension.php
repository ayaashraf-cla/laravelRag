<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// This file overrides pgvector/pgvector's vendor migration of the same name.
// That migration runs on the default connection, which may be MySQL, making
// CREATE/DROP EXTENSION fail. Our package handles the extension via the
// document_chunks migration on the correct pgsql connection instead.
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement('DROP EXTENSION IF EXISTS vector');
    }
};
