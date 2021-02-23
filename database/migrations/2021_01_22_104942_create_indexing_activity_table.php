<?php declare(strict_types=1);

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
        Schema::create('indexing_activities', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('trigger')->nullable();
            $table->dateTime('timestamp');
            $table->bigInteger('plan_id')->unsigned()->index();
            $table->bigInteger('project_id')->unsigned()->index();
            $table->timestamps();
        });

        Schema::table('indexing_activities', function ($table) {
            $table->foreign('plan_id')->references('id')->on('indexing_plans');
            $table->foreign('project_id')->references('id')->on('projects');
        });
    }
}
