<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemovetSubscriptionPlanColumnFromProject extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign([ 'subscription_plan_id' ]);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('subscription_plan_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->bigInteger('subscription_plan_id')->unsigned()->index();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreign('subscription_plan_id')->references('id')->on('subscription_plans');
        });
    }
}
