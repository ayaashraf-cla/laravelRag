<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('rag.documents_connection', 'mysql'))->table('documents', function (Blueprint $table) {
            if (! Schema::connection(config('rag.documents_connection', 'mysql'))->hasColumn('documents', 'section_id')) {
                $table->string('section_id')->nullable()->after('uploaded_by');
            }
        });
    }

    public function down(): void
    {
        Schema::connection(config('rag.documents_connection', 'mysql'))->table('documents', function (Blueprint $table) {
            $table->dropColumn('section_id');
        });
    }
};
