<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('payment_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('description', 255)->nullable();
            $table->string('ledger_role', 20); // monthly_fee | penalty | none
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        $now = now();

        DB::table('payment_methods')->insert([
            ['name' => 'M-Koba', 'description' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Bank Deposit', 'description' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('payment_types')->insert([
            ['name' => 'Monthly Fee', 'description' => null, 'ledger_role' => 'monthly_fee', 'is_active' => true, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Fines / Penalties', 'description' => null, 'ledger_role' => 'penalty', 'is_active' => true, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Loan Repayment', 'description' => null, 'ledger_role' => 'none', 'is_active' => true, 'is_system' => false, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // Add nullable first so the backfill below can run against existing rows.
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('payment_method_id')->nullable()->after('member_id')->constrained('payment_methods')->restrictOnDelete();
            $table->foreignId('payment_type_id')->nullable()->after('payment_method_id')->constrained('payment_types')->restrictOnDelete();
        });

        $mKobaId = DB::table('payment_methods')->where('name', 'M-Koba')->value('id');
        $bankId = DB::table('payment_methods')->where('name', 'Bank Deposit')->value('id');
        $monthlyFeeId = DB::table('payment_types')->where('name', 'Monthly Fee')->value('id');

        DB::table('payments')->where('reference', 'like', 'MKOBA-%')->update(['payment_method_id' => $mKobaId]);
        DB::table('payments')->where(function ($query) {
            $query->whereNull('reference')->orWhere('reference', 'not like', 'MKOBA-%');
        })->update(['payment_method_id' => $bankId]);
        DB::table('payments')->update(['payment_type_id' => $monthlyFeeId]);

        // MySQL refuses to MODIFY a column straight to NOT NULL while it's still part of a
        // foreign key (error 1832), so the constraints come off first and go back on after.
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['payment_method_id']);
            $table->dropForeign(['payment_type_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_method_id')->nullable(false)->change();
            $table->unsignedBigInteger('payment_type_id')->nullable(false)->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->restrictOnDelete();
            $table->foreign('payment_type_id')->references('id')->on('payment_types')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['payment_method_id']);
            $table->dropForeign(['payment_type_id']);
            $table->dropColumn(['payment_method_id', 'payment_type_id']);
        });

        Schema::dropIfExists('payment_types');
        Schema::dropIfExists('payment_methods');
    }
};
