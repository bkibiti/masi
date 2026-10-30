<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('member_id');
            $table->foreign('member_id')->references('id')->on('members')->restrictOnDelete();
            $table->foreignId('loan_type_id')->constrained('loan_types')->restrictOnDelete();

            $table->decimal('requested_amount', 12, 2);
            $table->unsignedInteger('requested_duration_months');
            $table->text('purpose')->nullable();

            $table->decimal('approved_amount', 12, 2)->nullable();
            $table->unsignedInteger('approved_duration_months')->nullable();

            // Copied from the loan type at application time; changing the loan type later
            // never changes an existing loan.
            $table->string('interest_method', 20);
            $table->decimal('interest_rate', 5, 2);
            $table->decimal('penalty_percent', 5, 2);

            $table->string('status', 20)->default('pending_approval');

            $table->decimal('applicant_contributions_total', 12, 2)->default(0);
            $table->decimal('required_guarantee_amount', 12, 2)->default(0);

            $table->unsignedInteger('applied_by')->nullable();
            $table->foreign('applied_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();

            $table->unsignedInteger('approved_by')->nullable();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->unsignedInteger('rejected_by')->nullable();
            $table->foreign('rejected_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->unsignedInteger('cancelled_by')->nullable();
            $table->foreign('cancelled_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();

            $table->unsignedInteger('disbursed_by')->nullable();
            $table->foreign('disbursed_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('disbursed_at')->nullable();
            $table->date('disbursed_on')->nullable();
            $table->foreignId('disbursement_payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->string('disbursement_reference', 150)->nullable();
            $table->text('disbursement_notes')->nullable();

            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
