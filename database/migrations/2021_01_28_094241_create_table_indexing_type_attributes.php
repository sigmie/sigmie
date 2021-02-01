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
        Schema::create('indexing_type_file', function (Blueprint $table) {
            $table->id();
            $table->string('location');
            $table->string('index_alias');
            $table->timestamps();
        });
    }
}
