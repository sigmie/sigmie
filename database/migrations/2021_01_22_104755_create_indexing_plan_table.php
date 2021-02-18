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
            $table->string('random_identifier');
            $table->string('description')->nullable();
            $table->bigInteger('cluster_id')->unsigned()->index();
            $table->bigInteger('project_id')->unsigned()->index();
            $table->bigInteger('user_id')->unsigned()->index();
            $table->string('type_type');
            $table->integer('type_id');
            $table->string('ping_url')->nullable();
            $table->string('state');
            $table->dateTime('run_at')->nullable();
            $table->dateTime('deactivated_at')->nullable()->default(null);
            $table->timestamps();
        });

        Schema::table('indexing_plans', function (Blueprint $table) {
            $table->foreign('cluster_id')->references('id')->on('clusters');
            $table->foreign('project_id')->references('id')->on('projects');
            $table->foreign('user_id')->references('id')->on('users');
            $table->unique(['type_type', 'type_id']);
            $table->unique(['project_id', 'random_identifier']);
        });
    }
}
