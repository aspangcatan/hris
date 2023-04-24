<?php

namespace App\Http\Controllers;

use App\Models\LeaveBalanceReport;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function leaveBalance(Request $request)
    {
        try {
            $results = LeaveBalanceReport::get(['name', 'period', 'vl_bal', 'sl_bal', 'cto_bal', 'fl_bal', 'spl_bal']);
            return response(['code' => 200, 'results' => $results]);
        } catch (\Exception $exception) {
            return response(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }
}
