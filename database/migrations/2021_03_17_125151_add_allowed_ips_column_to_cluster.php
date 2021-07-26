<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAllowedIpsColumnToCluster extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('allowed_ips', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('ip');
            $table->unsignedBigInteger('cluster_id')->nullable(false);
            $table->timestamps();
        });

        Schema::table('allowed_ips', function (Blueprint $table) {
            $table->foreign('cluster_id')->references('id')->on('clusters');
            $table->unique(['name', 'cluster_id']);
        });

        Schema::create('proxy_requests', function (Blueprint $table) {
            $table->json('request');
            $table->json('response');
            $table->unsignedBigInteger('cluster_id')->nullable(false);
        });

        Schema::table('proxy_requests', function (Blueprint $table) {
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
        Schema::table('cluster', function (Blueprint $table) {
            //
        });
    }
}
