<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RefactorMorphMap extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("UPDATE cluster_tokens c SET c.tokenable_type = 'cluster' WHERE c.tokenable_type = 'App\Models\Cluster';");
        DB::statement("UPDATE subscriptions s SET s.billable_type = 'user' WHERE s.billable_type = 'App\Models\User';");
        DB::statement("UPDATE receipts r SET r.billable_type = 'user' WHERE r.billable_type = 'App\Models\User';");
        DB::statement("UPDATE notifications n SET n.notifiable_type = 'user' WHERE n.notifiable_type = 'App\Models\User';");
        DB::statement("UPDATE customers c SET c.billable_type = 'user' WHERE c.billable_type = 'App\Models\User';");
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
