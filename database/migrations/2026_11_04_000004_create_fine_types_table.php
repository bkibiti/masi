<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fine_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->decimal('default_amount', 12, 2)->default(0);
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();

        DB::table('fine_types')->insert([
            ['name' => 'Meeting absence', 'default_amount' => 0, 'description' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Late arrival', 'default_amount' => 0, 'description' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Other', 'default_amount' => 0, 'description' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('fine_types');
    }
};
