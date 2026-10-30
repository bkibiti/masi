<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->date('due_date');
            $table->decimal('principal', 12, 2);
            $table->decimal('interest', 12, 2);
            $table->decimal('amount', 12, 2);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['loan_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_installments');
    }
};
