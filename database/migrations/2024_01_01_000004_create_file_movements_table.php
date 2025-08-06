<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('file_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->onDelete('cascade');
            $table->foreignId('from_location_id')->nullable()->constrained('locations')->onDelete('set null');
            $table->foreignId('to_location_id')->constrained('locations')->onDelete('cascade');
            $table->foreignId('moved_by')->constrained('users')->onDelete('cascade');
            $table->text('reason')->nullable();
            $table->timestamp('moved_at');
            $table->timestamps();
            
            $table->index(['file_id', 'moved_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('file_movements');
    }
};