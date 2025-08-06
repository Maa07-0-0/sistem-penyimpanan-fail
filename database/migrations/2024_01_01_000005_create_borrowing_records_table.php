<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('borrowing_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->onDelete('cascade');
            $table->foreignId('borrower_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('purpose');
            $table->date('borrowed_date');
            $table->date('due_date');
            $table->date('returned_date')->nullable();
            $table->foreignId('returned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('status', ['dipinjam', 'dikembalikan', 'overdue'])->default('dipinjam')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['file_id', 'status']);
            $table->index(['borrower_id', 'borrowed_date']);
            $table->index(['due_date', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('borrowing_records');
    }
};