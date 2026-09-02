<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * leave_credits/credit_earned_logs were MyISAM, so DB::transaction()
     * around the earned-credits balance update + audit log write was a
     * silent no-op. No column changes - engine only.
     */
    public function up()
    {
        DB::statement('ALTER TABLE leave_credits ENGINE=InnoDB');
        DB::statement('ALTER TABLE credit_earned_logs ENGINE=InnoDB');
    }

    public function down()
    {
        DB::statement('ALTER TABLE leave_credits ENGINE=MyISAM');
        DB::statement('ALTER TABLE credit_earned_logs ENGINE=MyISAM');
    }
};
