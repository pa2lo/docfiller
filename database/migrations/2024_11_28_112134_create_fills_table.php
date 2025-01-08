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
        Schema::create('fills', function (Blueprint $table) {
            $table->id();
			$table->foreignId('document_id')->nullable()->constrained()->cascadeOnDelete();
			$table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
			$table->json('addressData');
			$table->text('note')->nullable();
			$table->string('fill_type', 20)->nullable();
			$table->string('import_file')->nullable();
			$table->string('generated_file')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fills');
    }
};
