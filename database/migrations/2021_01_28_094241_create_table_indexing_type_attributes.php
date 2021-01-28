<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTableIndexingTypeAttributes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('indexing_plan_details', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('value');
            $table->bigInteger('indexing_plan_id')->unsigned()->index();
            $table->timestamps();
        });

        Schema::table('indexing_plan_details', function ($table) {
            $table->foreign('indexing_plan_id')->references('id')->on('indexing_plans');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
}
