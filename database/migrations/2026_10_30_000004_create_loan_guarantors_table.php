<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_guarantors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->unsignedInteger('guarantor_member_id');
            $table->foreign('guarantor_member_id')->references('id')->on('members')->restrictOnDelete();
            $table->decimal('guaranteed_amount', 12, 2);
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('responded_by')->nullable();
            $table->foreign('responded_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['loan_id', 'guarantor_member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_guarantors');
    }
};
