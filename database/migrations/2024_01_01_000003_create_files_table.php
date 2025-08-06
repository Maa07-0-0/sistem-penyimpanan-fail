<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->string('file_id')->unique()->index();
            $table->string('title');
            $table->string('reference_number')->nullable()->index();
            $table->year('document_year')->index();
            $table->string('department')->index();
            $table->enum('document_type', ['surat_rasmi', 'perjanjian', 'permit', 'laporan', 'lain_lain'])->index();
            $table->text('description')->nullable();
            $table->enum('status', ['tersedia', 'dipinjam', 'arkib', 'tidak_aktif'])->default('tersedia')->index();
            $table->foreignId('location_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index(['title', 'reference_number']);
            $table->index(['department', 'document_year']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('files');
    }
};