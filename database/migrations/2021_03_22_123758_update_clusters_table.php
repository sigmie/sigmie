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
        });

        Schema::table('clusters', function (Blueprint $blueprint) {
            $blueprint->integer('memory');
            $blueprint->integer('cores');
            $blueprint->integer('disk');
        });

        Schema::create('project_cluster_rel', function (Blueprint $blueprint) {
            $blueprint->bigInteger('project_id')->unsigned()->index()->nullable();
            $blueprint->unsignedBigInteger('cluster_id')->nullable();
            $blueprint->string('cluster_type')->nullable();
            $blueprint->timestamps();
        });

        Schema::table('project_cluster_rel', function (Blueprint $blueprint) {
            $blueprint->unique(['cluster_type', 'cluster_id']);
        });

        Schema::table('indexing_plans', function (Blueprint $blueprint) {
            $blueprint->string('cluster_type')->nullable();
            $blueprint->dropConstrainedForeignId('cluster_id');
        });

        Schema::table('indexing_plans', function (Blueprint $blueprint) {
            $blueprint->unsignedBigInteger('cluster_id')->nullable();
        });

        Schema::create('external_clusters', function (Blueprint $blueprint) {
            $blueprint->increments('id');
            $blueprint->string('name')->unique();
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
