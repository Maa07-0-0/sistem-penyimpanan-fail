<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('room')->index();
            $table->string('rack')->index();
            $table->string('slot')->index();
            $table->string('description')->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();
            
            $table->unique(['room', 'rack', 'slot']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('locations');
    }
};