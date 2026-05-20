<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection(config('rag.documents_connection', 'mysql'))->create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('original_name');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->nullableMorphs('documentable');
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->boolean('is_visible')->default(false);
            $table->string('status')->default('queued')->index();
            $table->longText('extracted_text')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('rag.documents_connection', 'mysql'))->dropIfExists('documents');
    }
};
