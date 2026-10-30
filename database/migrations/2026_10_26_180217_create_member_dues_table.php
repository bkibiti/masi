<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_dues', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('member_id');
            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
            $table->foreignId('contribution_setting_id')->constrained()->cascadeOnDelete();
            $table->date('period');
            $table->date('due_date');
            $table->decimal('amount', 12, 2);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['member_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_dues');
    }
};
