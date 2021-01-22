<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIndexingActivityTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('indexing_activity', function (Blueprint $table) {
            $table->id();
            $table->string('trigger');
            $table->bigInteger('plan_id')->unsigned()->index();
            $table->timestamps();
        });

        Schema::table('indexing_activity', function ($table) {
            $table->foreign('plan_id')->references('id')->on('indexing_plan');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('indexing_activity');
    }
}
