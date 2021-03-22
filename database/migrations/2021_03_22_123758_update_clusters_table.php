<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateClustersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('projects', function (Blueprint $blueprint) {
            $blueprint->unsignedBigInteger('cluster_id')->nullable();
            $blueprint->string('cluster_type')->nullable();
        });

        Schema::create('external_clusters', function (Blueprint $blueprint) {
            $blueprint->increments('id');
            $blueprint->bigInteger('name_id')->unsigned()->index()->nullable();
            $blueprint->string('username');
            $blueprint->string('password');
            $blueprint->string('state');
            $blueprint->timestamps();
            $blueprint->bigInteger('project_id')->unsigned()->index();
            $blueprint->boolean('search_token_active');
            $blueprint->boolean('admin_token_active');
            $blueprint->string('url');
        });

        Schema::create('cluster_names', function (Blueprint $blueprint) {
            $blueprint->string('cluster_id');
            $blueprint->string('cluster_type');
            $blueprint->string('name')->unique();
            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
