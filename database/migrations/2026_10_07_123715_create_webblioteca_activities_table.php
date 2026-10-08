<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('webblioteca_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('external_id')->unique(); // id do evento na API da Webblioteca
            $table->string('type');
            $table->json('summary')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webblioteca_activities');
    }
};
