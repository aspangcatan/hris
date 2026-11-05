<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * leave_details lost its leave type link in 2026_05_29, so details were matched to leave
 * types by position - and the filing, view/edit and print code each ordered the types
 * differently, putting details under the wrong type on multi-type applications.
 * This puts the link back and fills it for existing rows from the detail text.
 */
class AddLeaveTypeIdToLeaveDetailsTable extends Migration
{
    /** Detail option label => keywords of the leave types it belongs to (as in the filing form). */
    private const OPTION_KEYWORDS = [
        'Within the Philippines'        => ['vacation leave', 'special privilege'],
        'Abroad (Specify)'              => ['vacation leave', 'special privilege'],
        'In Hospital (Specify Illness)' => ['sick leave'],
        'Out Patient (Specify Illness)' => ['sick leave'],
        '(Specify Illness)'             => ['special leave benefits for women'],
        "Completion of Master's Degree" => ['study leave'],
        'BAR/Board Examination Review'  => ['study leave'],
    ];

    public function up()
    {
        Schema::table('leave_details', function (Blueprint $table) {
            $table->unsignedBigInteger('leave_type_id')->nullable()->after('leave_application_id');
            $table->index('leave_type_id');
        });

        $typeNames = DB::table('leave_types')->pluck('description', 'id')->map(fn($d) => strtolower((string) $d));

        $unresolved = 0;
        foreach (DB::table('leave_details')->orderBy('id')->get()->groupBy('leave_application_id') as $appId => $rows) {
            $appTypeIds = DB::table('leave_date_applied')
                ->where('leave_application_id', $appId)
                ->distinct()
                ->pluck('leave_type_id')
                ->filter()
                ->map(fn($id) => (int) $id)
                ->all();

            $used = [];
            foreach ($rows as $row) {
                $keywords = self::OPTION_KEYWORDS[$row->detail] ?? [];
                $match = null;
                foreach ($appTypeIds as $typeId) {
                    if (in_array($typeId, $used, true)) continue;
                    foreach ($keywords as $kw) {
                        if (strpos($typeNames[$typeId] ?? '', $kw) !== false) { $match = $typeId; break 2; }
                    }
                }

                if ($match === null) {
                    $unresolved++;
                    continue;
                }
                $used[] = $match;
                DB::table('leave_details')->where('id', $row->id)->update(['leave_type_id' => $match]);
            }
        }

        if ($unresolved > 0 && app()->runningInConsole()) {
            echo "  leave_details: {$unresolved} row(s) could not be matched to a leave type (left NULL - check them).\n";
        }
    }

    public function down()
    {
        Schema::table('leave_details', function (Blueprint $table) {
            $table->dropIndex(['leave_type_id']);
            $table->dropColumn('leave_type_id');
        });
    }
}
