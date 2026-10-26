<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGeneralSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('general_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('pharmacy_name')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('TIN_number')->nullable();
            $table->string('VRN_number')->nullable();
            $table->string('slogan')->nullable();
            $table->string('logo')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('make_batch_number_mandatory')->default('No');
            $table->string('make_customer_name_mandatory')->default('No');
            $table->string('good_receive_option')->default('Total cost/Qty');
            $table->string('enable_cashflow_feature')->default('No');
            $table->string('enable_back_date_sell')->default('No');
            $table->string('make_invoice_number_mandatory')->default('No');
            $table->integer('number_of_copies')->default(1);
            $table->string('recept_printing')->default('None');
            $table->string('location_printing')->default('None');
            $table->string('recept_size')->default('None');
            $table->integer('max_number_of_attempt')->default(1000);//counts
            $table->integer('auto_logOff_time')->default(1000);//minutes
            $table->integer('system_alert_interval')->default(60);//minutes
            $table->integer('password_expery')->default(12);//months
            $table->integer('password_expery_alert')->default(10);//days
            $table->integer('min_password_complexity')->default(8);//characters
            $table->decimal('VAT_or_TAX',15,2)->nullable();
            $table->string('created_by')->default('Apotek general Settings');
            $table->string('updated_by')->default('Apotek general Settings');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('general_settings');
    }
}
