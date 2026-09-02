<?php

namespace Tests\Feature;

use App\Services\LeaveCreditService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LeaveCreditBalanceTransactionTest extends TestCase
{
    // Deliberately no DatabaseTransactions here: this test proves a real
    // DB::transaction() rollback via a temporary trigger, and CREATE/DROP
    // TRIGGER are DDL statements that MySQL implicitly commits any open
    // transaction around - nesting this inside PHPUnit's own wrapping
    // transaction desyncs Laravel's transaction-depth tracking and corrupts
    // later tests in the same run. Clean up manually instead, same as every
    // other MyISAM-adjacent test in this project.

    private int $userId = 9602;

    protected function tearDown(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS credit_earned_logs_fail_sentinel');
        DB::table('hris.leave_credits')->where('user_id', $this->userId)->delete();
        DB::table('credit_earned_logs')->where('user_id', $this->userId)->delete();
        parent::tearDown();
    }

    /** @test */
    public function a_failed_audit_log_insert_rolls_back_the_balance_update()
    {
        // A real, temporary trigger forces a genuine DB-level failure on the
        // second write (credit_earned_logs), so this proves the actual
        // transaction/rollback behavior end-to-end rather than mocking it.
        DB::statement("
            CREATE TRIGGER credit_earned_logs_fail_sentinel
            BEFORE INSERT ON credit_earned_logs
            FOR EACH ROW
            BEGIN
                IF NEW.total_lateundertime_minutes = 999999 THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Forced failure for transaction test';
                END IF;
            END
        ");

        try {
            app(LeaveCreditService::class)->updateEarnedCredits(
                $this->userId, 2027, 8, 20, 999999, 1.25, 0
            );
            $this->fail('Expected the forced trigger failure to propagate.');
        } catch (\Throwable $e) {
            // expected
        }

        $this->assertFalse(
            DB::table('hris.leave_credits')->where('user_id', $this->userId)->exists(),
            'leave_credits must not be written when the paired audit log insert fails.'
        );
        $this->assertFalse(
            DB::table('credit_earned_logs')->where('user_id', $this->userId)->exists()
        );
    }
}
