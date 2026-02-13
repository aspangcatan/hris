<?php

namespace App\Http\Controllers;

use App\Models\Declarant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SalnAdminController extends Controller
{
    /**
     * Display the SALN Admin dashboard.
     */
    public function index()
    {
        // Check if user has admin/hr privileges
        if (!in_array(session('access_rights'), ['admin', 'hr', 'HRH'])) {
            return redirect()->route('profile')->with('error', 'Unauthorized access.');
        }

        return view('saln_admin');
    }

    /**
     * Get list of employees with their SALN filing status.
     */
    public function getEmployees(Request $request)
    {
        try {
            $search = $request->search;
            $year = $request->year ?? date('Y');
            $perPage = $request->per_page ?? 6;

            $query = User::query()
                ->leftJoin('hris.employees as e', 'users.id', '=', 'e.user_id')
                ->leftJoin('hris.saln_declarant as d', function($join) use ($year) {
                    $join->on('users.id', '=', 'd.user_id')
                         ->whereYear('d.compliance_for', '=', $year);
                })
                ->select([
                    'users.id', 'users.fname', 'users.mname', 'users.lname', 'users.suffix',
                    'e.employee_no',
                    'd.compliance_for', 'd.id as declarant_id', 'd.filing_type',
                    // Optimization: Subqueries for assets/liabilities only run for the paginated result set
                    DB::raw("(SELECT COALESCE(SUM(acquisition_cost), 0) FROM hris.real_properties WHERE user_id = users.id AND YEAR(compliance_for) = $year) as real_assets"),
                    DB::raw("(SELECT COALESCE(SUM(acquisition_cost), 0) FROM hris.personal_properties WHERE user_id = users.id AND YEAR(compliance_for) = $year) as personal_assets"),
                    DB::raw("(SELECT COALESCE(SUM(outstanding_balance), 0) FROM hris.liabilities WHERE user_id = users.id AND YEAR(compliance_for) = $year) as liabilities"),
                    DB::raw("(SELECT COALESCE(SUM(acquisition_cost), 0) FROM hris.spouse_real_properties WHERE user_id = users.id AND YEAR(compliance_for) = $year) as spouse_real_assets"),
                    DB::raw("(SELECT COALESCE(SUM(acquisition_cost), 0) FROM hris.spouse_personal_properties WHERE user_id = users.id AND YEAR(compliance_for) = $year) as spouse_personal_assets"),
                    DB::raw("(SELECT COALESCE(SUM(outstanding_balance), 0) FROM hris.spouse_liabilities WHERE user_id = users.id AND YEAR(compliance_for) = $year) as spouse_liabilities"),
                ])
                ->where('users.username', '!=', 'admin');

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('users.fname', 'like', "%$search%")
                      ->orWhere('users.mname', 'like', "%$search%")
                      ->orWhere('users.lname', 'like', "%$search%")
                      ->orWhere('e.employee_no', 'like', "%$search%");
                });
            }

            // Standard Eloquent Pagination
            $employees = $query->orderBy('users.lname', 'asc')->paginate($perPage);

            // Use the .through() pattern (Eloquent) to transform the current page items only
            $employees->through(function($emp) {
                $assets = $emp->real_assets + $emp->personal_assets;
                $liabilities = $emp->liabilities;
                
                if ($emp->filing_type === 'Joint Filing') {
                    $assets += ($emp->spouse_real_assets + $emp->spouse_personal_assets);
                    $liabilities += $emp->spouse_liabilities;
                }
                
                $emp->total_assets = $assets;
                $emp->total_liabilities = $liabilities;
                $emp->net_worth = $assets - $liabilities;
                return (object) $emp;
            });

            return response()->json([
                'code' => 200,
                'results' => $employees->items(),
                'pagination' => [
                    'current_page' => $employees->currentPage(),
                    'last_page' => $employees->lastPage(),
                    'total' => $employees->total(),
                    'per_page' => $employees->perPage(),
                ],
                'year' => $year
            ]);
        } catch (\Exception $exception) {
            return response()->json(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    /**
     * Get SALN history for a specific employee.
     */
    public function getEmployeeSalnHistory($userId)
    {
        try {
            $history = \App\Models\Declarant::where('user_id', $userId)
                ->orderBy('compliance_for', 'desc')
                ->get();
                
            // Also calculate net worth for history items if possible
            $history->transform(function($record) use ($userId) {
                $compDate = $record->compliance_for;
                $compYear = date('Y', strtotime($compDate));
                
                $real_assets = \App\Models\RealProperties::where('user_id', $userId)->whereYear('compliance_for', $compYear)->sum('acquisition_cost');
                $personal_assets = \App\Models\PersonalProperties::where('user_id', $userId)->whereYear('compliance_for', $compYear)->sum('acquisition_cost');
                $liabilities = \App\Models\Liabilities::where('user_id', $userId)->whereYear('compliance_for', $compYear)->sum('outstanding_balance');
                
                $assets = $real_assets + $personal_assets;
                
                if ($record->filing_type === 'Joint Filing') {
                    $assets += \App\Models\SpouseRealProperties::where('user_id', $userId)->whereYear('compliance_for', $compYear)->sum('acquisition_cost');
                    $assets += \App\Models\SpousePersonalProperties::where('user_id', $userId)->whereYear('compliance_for', $compYear)->sum('acquisition_cost');
                    $liabilities += \App\Models\SpouseLiabilities::where('user_id', $userId)->whereYear('compliance_for', $compYear)->sum('outstanding_balance');
                }
                
                $record->net_worth = $assets - $liabilities;
                return $record;
            });

            return response()->json([
                'code' => 200,
                'results' => $history
            ]);
        } catch (\Exception $exception) {
            return response()->json(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    /**
     * Update filing type for an employee's SALN record.
     */
    public function updateFilingType(Request $request)
    {
        try {
            $request->validate([
                'user_id' => 'required|exists:App\Models\User,id',
                'year' => 'required|integer',
                'filing_type' => 'required|in:Joint Filing,Separate Filing,Not Applicable'
            ]);

            $userId = $request->user_id;
            $year = $request->year;
            $filingType = $request->filing_type;

            // Find the record
            $declarant = \App\Models\Declarant::where('user_id', $userId)
                ->whereYear('compliance_for', $year)
                ->first();

            if (!$declarant) {
                return response()->json(['code' => 404, 'message' => 'SALN record not found for this employee and year.']);
            }

            $declarant->filing_type = $filingType;
            $declarant->save();

            return response()->json([
                'code' => 200,
                'message' => 'Filing type updated successfully.'
            ]);
        } catch (\Exception $exception) {
            return response()->json(['code' => 500, 'message' => $exception->getMessage()]);
        }
    }

    /**
     * Generate a summary report of SALN filings for a specific year.
     */
    public function generateSummaryReport(Request $request)
    {
        try {
            $year = $request->year ?? date('Y');
            
            // Get total personnel complement (all active users except admin)
            $totalPersonnel = DB::table('tdh_user.users')
                ->where('username', '!=', 'admin')
                ->count();

            // Get all employees who filed for this year
            $employees = DB::table('tdh_user.users as u')
                ->leftJoin('hris.employees as e', 'u.id', '=', 'e.user_id')
                ->leftJoin('tdh_user.designation as des', 'u.designation', '=', 'des.id')
                ->join('hris.saln_declarant as d', function($join) use ($year) {
                    $join->on('u.id', '=', 'd.user_id')
                         ->whereYear('d.compliance_for', '=', $year);
                })
                ->select([
                    'u.id', 'u.fname', 'u.mname', 'u.lname', 'u.suffix',
                    'e.employee_no', 'e.tin_no',
                    'des.description as position',
                    'd.compliance_for', 'd.filing_type', 'd.compliance_type'
                ])
                ->where('u.username', '!=', 'admin')
                ->orderBy('u.lname', 'asc')
                ->get();

            $totalFilers = $employees->count();

            // Calculate financials for each
            $summary = $employees->map(function($emp) use ($year) {
                // Fetch assets/liabilities dynamically
                $real = DB::table('hris.real_properties')->where('user_id', $emp->id)->whereYear('compliance_for', $year)->sum('acquisition_cost');
                $personal = DB::table('hris.personal_properties')->where('user_id', $emp->id)->whereYear('compliance_for', $year)->sum('acquisition_cost');
                $liabilities = DB::table('hris.liabilities')->where('user_id', $emp->id)->whereYear('compliance_for', $year)->sum('outstanding_balance');
                
                $spouseReal = 0; $spousePersonal = 0; $spouseLiabilities = 0;
                
                if ($emp->filing_type === 'Joint Filing') {
                    $spouseReal = DB::table('hris.spouse_real_properties')->where('user_id', $emp->id)->whereYear('compliance_for', $year)->sum('acquisition_cost');
                    $spousePersonal = DB::table('hris.spouse_personal_properties')->where('user_id', $emp->id)->whereYear('compliance_for', $year)->sum('acquisition_cost');
                    $spouseLiabilities = DB::table('hris.spouse_liabilities')->where('user_id', $emp->id)->whereYear('compliance_for', $year)->sum('outstanding_balance');
                }

                $totalAssets = $real + $personal + $spouseReal + $spousePersonal;
                $totalLiabilities = $liabilities + $spouseLiabilities;
                $netWorth = $totalAssets - $totalLiabilities;

                return (object)[
                    'lname' => strtoupper($emp->lname),
                    'fname' => strtoupper($emp->fname),
                    'mname' => strtoupper($emp->mname ?? ''),
                    'suffix' => strtoupper($emp->suffix ?? ''),
                    'employee_no' => $emp->employee_no ?? 'N/A',
                    'tin_no' => $emp->tin_no ?? '',
                    'position' => $emp->position ?? 'N/A',
                    'filing_type' => $emp->filing_type ?? 'N/A',
                    'net_worth' => $netWorth
                ];
            });

            return view('reports.saln_summary', [
                'year' => $year,
                'records' => $summary,
                'total_filers' => $totalFilers,
                'total_personnel' => $totalPersonnel,
                'print_date' => now()->format('F d, Y')
            ]);
        } catch (\Exception $exception) {
            return response($exception->getMessage(), 500);
        }
    }
}
