<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIndexingPlanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('indexing_plan', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description');
            $table->bigInteger('cluster_id')->unsigned()->index();
            $table->string('type');
            $table->string('state')->default('none');
            $table->dateTime('run_at');
            $table->dateTime('deactivated_at')->nullable()->default(null);
            $table->timestamps();
        });

        Schema::table('indexing_plan', function ($table) {
            $table->foreign('cluster_id')->references('id')->on('clusters');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('indexing_plan');
    }
}
