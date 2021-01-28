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
        Schema::create('indexing_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description');
            $table->string('frequency');
            $table->bigInteger('cluster_id')->unsigned()->index();
            $table->string('type');
            $table->string('webhook_url')->nullable();
            $table->string('state')->default('none');
            $table->dateTime('run_at')->nullable();
            $table->dateTime('deactivated_at')->nullable()->default(null);
            $table->timestamps();
        });

        Schema::table('indexing_plans', function ($table) {
            $table->foreign('cluster_id')->references('id')->on('clusters');
        });
    }
}
