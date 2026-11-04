<?php

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\HospitalOrderController;
use App\Http\Controllers\OvertimeController;
use App\Http\Controllers\PassSlipApproverController;
use App\Http\Controllers\PassSlipController;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    if (Auth::check()) return redirect()->route('profile');
    return view('login');
})->name('login');

Route::get('/logout', function () {
    Auth::logout();
    Session::flush();
    return redirect()->route('login');
})->name('logout');





Route::post('/authenticate', [\App\Http\Controllers\UserController::class, 'authenticate'])->name('authenticate');
Route::get('/get_related_work', [\App\Http\Controllers\PromotionWorkController::class, 'getRelatedWork'])->name('getRelatedWork');

//MOBILE API
Route::post('/mobile/login', [\App\Http\Controllers\DTRController::class, 'login'])->name('mobileLogin');
Route::post('/dtr/store', [\App\Http\Controllers\DTRController::class, 'storeDtr'])->name('storeDtr');
Route::get('/user/info', [\App\Http\Controllers\DTRController::class, 'getUserInfo'])->name('getUserInfo');
Route::post('/user/verify/pin', [\App\Http\Controllers\DTRController::class, 'verifyPin'])->name('verifyPin');
Route::get('/dtr_logs', [\App\Http\Controllers\DTRController::class, 'getDTRLogs'])->name('getDTRLogs');
Route::get('/dtr_3_logs', [\App\Http\Controllers\DTRController::class, 'getDTRLast3']);
Route::get('/dtr/latestlogs', [\App\Http\Controllers\DTRController::class, 'getLatestLog']);
Route::get('/dtr/recentlogs', [\App\Http\Controllers\DTRController::class, 'getRecentLogs']);
Route::get('/dtr/inoutlogs', [\App\Http\Controllers\DTRController::class, 'getInOutLogs']);

Route::get('/apk/download/old', function () {
    return response()->file(public_path('apk/mobile-dtr.apk'), [
        'Content-Type' => 'application/vnd.android.package-archive',
        'Content-Disposition' => 'attachment; filename=mobile_dtr_old_version.apk',
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        'Pragma' => 'no-cache',
        'Expires' => '0',
    ]);
})->name('download.old_version');

Route::get('/apk/download', function () {
    return response()->file(public_path('apk/mobile_dtr_v3.1.2.apk'), [
        'Content-Type' => 'application/vnd.android.package-archive',
        'Content-Disposition' => 'attachment; filename=mobile_dtr_v3.1.2.apk',
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        'Pragma' => 'no-cache',
        'Expires' => '0',
    ]);
});

Route::get('/apk/download/v1', function () {
    return response()->file(public_path('apk/mobile_dtr_v1.apk'), [
        'Content-Type' => 'application/vnd.android.package-archive',
        'Content-Disposition' => 'attachment; filename=mobile_dtr_v1.apk',
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        'Pragma' => 'no-cache',
        'Expires' => '0',
    ]);
})->name('download.v1');

Route::get('/apk/download/v2', function () {
    return response()->file(public_path('apk/mobile_dtr_v2.apk'), [
        'Content-Type' => 'application/vnd.android.package-archive',
        'Content-Disposition' => 'attachment; filename=mobile_dtr_v2.apk',
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        'Pragma' => 'no-cache',
        'Expires' => '0',
    ]);
})->name('download.v2');

Route::get('/apk/download/latest', function () {
    return response()->file(public_path('apk/mobile_dtr_latest.apk'), [
        'Content-Type' => 'application/vnd.android.package-archive',
        'Content-Disposition' => 'attachment; filename=mobile_dtr_latest.apk',
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        'Pragma' => 'no-cache',
        'Expires' => '0',
    ]);
})->name('download.latest');

Route::get('/apk/download/test', function () {
    return response()->file(public_path('apk/mobile_dtr_test.apk'), [
        'Content-Type' => 'application/vnd.android.package-archive',
        'Content-Disposition' => 'attachment; filename=mobile_dtr_test.apk',
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        'Pragma' => 'no-cache',
        'Expires' => '0',
    ]);
})->name('download.test');

Route::get('/apk/download/facelog', function () {
    return response()->file(public_path('apk/facelog.apk'), [
        'Content-Type' => 'application/vnd.android.package-archive',
        'Content-Disposition' => 'attachment; filename=facelog.apk',
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        'Pragma' => 'no-cache',
        'Expires' => '0',
    ]);
});

Route::get('/get/employee', [\App\Http\Controllers\DTRController::class, 'getUserInfoById']);

Route::get('/get/totalPresent', [\App\Http\Controllers\CreditController::class, 'displayTotalPresent']);

Route::get('/download', function () {
    return redirect()->to(url('apk/csmc_mobile_dtr_v1.apk'));
})->name('download');

Route::get('/download_2', function () {
    return redirect()->to(url('apk/csmc_mobile_dtr_v2.apk'));
})->name('download_2');

Route::group(['middleware' => 'auth'], function () {
    #PRINTING
    Route::get('/pds', [\App\Http\Controllers\PrintController::class, 'printPds'])->name('printPds');
    Route::get('/service_records', [\App\Http\Controllers\PrintController::class, 'printServiceRecord'])->name('printServiceRecord');
    Route::get('/print_saln', [\App\Http\Controllers\PrintController::class, 'printSaln'])->name('printSaln');
    Route::get('/saln-report/pdf', [\App\Http\Controllers\PrintController::class, 'generateSALNReport']);
    Route::get('/print_work_experience', [\App\Http\Controllers\PrintController::class, 'printWorkExperience'])->name('printWorkExperience');

    #NAVIGATION
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/profile', function () {
        return view('profile');
    })->name('profile');

    Route::get('/saln', function () {
        return view('saln');
    })->name('saln')->middleware('permission:saln.view_own');

    Route::get('/employee', function () {
        return view('employee');
    })->name('employee')->middleware('permission:employee.view');

    Route::get('/dtr', function () {
        return view('dtr');
    })->name('dtr')->middleware('permission:dtr.view_own');

    Route::get('/pass_slip', function () {
        return view('pass_slip');
    })->name('pass_slip')->middleware('permission:passslip.file');

    Route::get('/employee_dtr', function () {
        return view('employee_dtr');
    })->name('employee_dtr')->middleware('permission:dtr.view_others');

    Route::get('/leave', function () {
        return view('leave');
    })->name('leave');

    Route::get('/leave_application', function () {
        return view('leave_application');
    })->name('leave_application')->middleware('permission:leave.file');

    Route::get('/leave_approval', function () {
        return view('leave_approval');
    })->name('leave_approval')->middleware('permission:leave.approve');

    Route::get('/promotion_applied', function () {
        return view('promotion_work_applied');
    })->name('promotion_applied');

    Route::get('/credit_management', function () {
        return view('credit_management');
    })->name('credit_management');

    Route::get('/schedule', function () {
        return view('schedule');
    })->name('schedule')->middleware('permission:schedule.view');
    Route::get('/schedule_master_list', function () {
        return view('schedule_master_list');
    })->name('schedule_master_list')->middleware('permission:schedule.manage');
    Route::get('/schedule_access', function () {
        return view('schedule_access');
    })->name('schedule_access');

    Route::get('/review_leave', function () {
        return view('review_leave');
    })->name('review_leave');

    #SETTINGS VIEW
    Route::get('/calendar', function () {
        return view('settings.calendar');
    })->name('calendar');

    Route::get('/hospital_order', function () {
        return view('hospital_order');
    })->name('hospital_order');
    Route::get('/hospital_order/create', function () {
        if (!in_array(session('access_rights'), ['admin'])) {
            abort(403);
        }
        return view('hospital_order_create');
    })->name('hospital_order_create');
    Route::get('/hospital_order/file', function () {
        return view('hospital_order_file');
    })->name('hospital_order_file')->middleware('permission:hospital_order.file');
    Route::get('/hospital_order/approval', function () {
        if (!in_array(session('access_rights'), ['admin'], true) && !session('is_section_head') && !session('is_division_head')) {
            abort(403);
        }
        return view('hospital_order_approval');
    })->name('hospital_order_approval')->middleware('permission:hospital_order.approve');
    Route::get('/overtime/file', function () {
        return view('overtime_file');
    })->name('overtime_file')->middleware('permission:overtime.file');
    Route::get('/overtime/approval', function () {
        if (!in_array(session('access_rights'), ['admin'], true) && !session('is_section_head') && !session('is_division_head')) {
            abort(403);
        }
        return view('overtime_approval');
    })->name('overtime_approval')->middleware('permission:overtime.approve');

    Route::get('/leave_management', function () {
        return view('settings.leave_management');
    })->name('leave_management');

    Route::get('/bulk-leave-credits', function () {
        return view('settings.bulk_leave_credits');
    })->name('bulk-leave-credits.index');
    Route::get('/bulk-leave-credits/batches',      [\App\Http\Controllers\LeaveBulkCreditController::class, 'getBatches'])->name('bulk-leave-credits.batches');
    Route::get('/bulk-leave-credits/divisions',    [\App\Http\Controllers\LeaveBulkCreditController::class, 'getDivisions'])->name('bulk-leave-credits.divisions');
    Route::get('/bulk-leave-credits/sections',     [\App\Http\Controllers\LeaveBulkCreditController::class, 'getSections'])->name('bulk-leave-credits.sections');
    Route::get('/bulk-leave-credits/employees',    [\App\Http\Controllers\LeaveBulkCreditController::class, 'getEmployees'])->name('bulk-leave-credits.employees');
    Route::get('/bulk-leave-credits/employee-types', [\App\Http\Controllers\LeaveBulkCreditController::class, 'getEmployeeTypes'])->name('bulk-leave-credits.employee-types');
    Route::get('/bulk-leave-credits/leave-types',    [\App\Http\Controllers\LeaveBulkCreditController::class, 'getAllLeaveTypes'])->name('bulk-leave-credits.leave-types');
    Route::post('/bulk-leave-credits',             [\App\Http\Controllers\LeaveBulkCreditController::class, 'store'])->name('bulk-leave-credits.store');
    Route::get('/bulk-leave-credits/{id}/print',   [\App\Http\Controllers\LeaveBulkCreditController::class, 'printBatch'])->name('bulk-leave-credits.print');

    Route::get('/payroll', function () {
        return view('payroll');
    })->name('payroll')->middleware('permission:payroll.view');
    Route::get('/payroll_settings', function () {
        return view('settings.payroll');
    })->name('payroll_settings')->middleware('permission:payroll.settings');
    Route::get('/payroll-components/manage', function () {
        return view('settings.payroll_components');
    })->name('payroll_components')->middleware('permission:payroll.settings');

    Route::get('/timetable', function () {
        return view('settings.timetable');
    })->name('timetable');


    Route::get('/get_credit_balance', [\App\Http\Controllers\LeaveController::class, 'getCreditBalances'])->name('getCreditBalances');
    Route::put('/update_base_credit', [\App\Http\Controllers\LeaveController::class, 'insertUpdateBaseCredit'])->name('insertUpdateBaseCredit')->middleware('permission:leave.manage_credits');
    Route::put('/update_used_credit', [\App\Http\Controllers\LeaveController::class, 'insertUpdateUsedCredit'])->name('insertUpdateUsedCredit');

    Route::get('/get/leave_types', [\App\Http\Controllers\LeaveController::class, 'getLeaveTypes'])->name('getLeaveTypes');
    Route::put('/update_leave_type', [\App\Http\Controllers\LeaveController::class, 'insertUpdateLeaveType'])->name('insertUpdateLeaveType');
    Route::delete('/delete/leave_type', [\App\Http\Controllers\LeaveController::class, 'deleteLeaveType'])->name('deleteLeaveType');

    //PAYROLL SETTINGS
    Route::get('/payroll-settings', [\App\Http\Controllers\PayrollController::class, 'getPayrollSettings'])->name('getPayrollSettings');
    Route::post('/save_payroll_settings', [\App\Http\Controllers\PayrollController::class, 'savePayrollSettings'])->name('savePayrollSettings');
    Route::get('/payroll-components', [\App\Http\Controllers\PayrollController::class, 'getPayrollComponents'])->name('getPayrollComponents');
    Route::post('/payroll-components', [\App\Http\Controllers\PayrollController::class, 'savePayrollComponents'])->name('savePayrollComponents');
    Route::get('/payroll-tax-rules/manage', function () {
        return view('settings.payroll_tax_rules');
    })->name('payroll_tax_rules')->middleware('permission:payroll.settings');
    Route::get('/payroll-tax-rules', [\App\Http\Controllers\PayrollController::class, 'getPayrollTaxRules'])->name('getPayrollTaxRules');
    Route::post('/payroll-tax-rules', [\App\Http\Controllers\PayrollController::class, 'savePayrollTaxRules'])->name('savePayrollTaxRules');
    Route::get('/payroll-assignatories/manage', function () {
        return view('settings.payroll_assignatories');
    })->name('payroll_assignatories')->middleware('permission:payroll.settings');
    Route::get('/payroll-assignatories', [\App\Http\Controllers\PayrollController::class, 'getPayrollAssignatories'])->name('getPayrollAssignatories')->middleware('permission:payroll.settings');
    Route::post('/payroll-assignatories', [\App\Http\Controllers\PayrollController::class, 'savePayrollAssignatories'])->name('savePayrollAssignatories')->middleware('permission:payroll.settings');
    Route::get('/payroll-assignatories/search-users', [\App\Http\Controllers\PayrollController::class, 'searchPayrollAssignatoryUsers'])->name('searchPayrollAssignatoryUsers')->middleware('permission:payroll.settings');
    Route::get('/cutoff-summaries/manage', function () {
        return view('settings.cutoff_summaries');
    })->name('cutoff_summaries')->middleware('permission:payroll.settings');
    Route::get('/cutoff-summaries', [\App\Http\Controllers\PayrollController::class, 'getCutoffSummaries'])->name('getCutoffSummaries');
    Route::post('/cutoff-summaries', [\App\Http\Controllers\PayrollController::class, 'createCutoffSummaries'])->name('createCutoffSummaries');
    Route::get('/cutoff-periods', [\App\Http\Controllers\PayrollController::class, 'getCutoffPeriods'])->name('getCutoffPeriods');
    Route::get('/cutoff-summary-preview', [\App\Http\Controllers\PayrollController::class, 'getCutoffSummaryPreview'])->name('getCutoffSummaryPreview');
    Route::get('/payroll/{payroll_id}/cutoff-employees', [\App\Http\Controllers\PayrollController::class, 'getCutoffEmployeesForPayroll'])->name('getCutoffEmployeesForPayroll');
    Route::get('/cutoff-leave-credits-preview', [\App\Http\Controllers\CreditController::class, 'previewCutoffLeaveCredits'])->name('previewCutoffLeaveCredits');
    Route::post('/cutoff-leave-credits-apply', [\App\Http\Controllers\CreditController::class, 'applyCutoffLeaveCredits'])->name('applyCutoffLeaveCredits');

    //PAYROLL
    Route::post('/save_payroll', [\App\Http\Controllers\PayrollController::class, 'savePayroll'])->name('savePayroll');
    Route::post('/save_payroll_employee', [\App\Http\Controllers\PayrollController::class, 'savePayrollEmployee'])->name('savePayrollEmployee');
    Route::post('/payroll/submit_for_approval', [\App\Http\Controllers\PayrollController::class, 'submitForApproval'])->name('submitPayrollForApproval');
    Route::post('/payroll/approval_action', [\App\Http\Controllers\PayrollController::class, 'approvalAction'])->name('payrollApprovalAction');
    Route::post('/payroll/release', [\App\Http\Controllers\PayrollController::class, 'releasePayroll'])->name('releasePayroll');
    Route::get('/get_payroll', [\App\Http\Controllers\PayrollController::class, 'getPayroll'])->name('getPayroll');
    Route::get('/get_employee_division_section', [\App\Http\Controllers\EmployeeController::class, 'getEmployeeByDivisionSection'])->name('getEmployeeByDivisionSection');
    Route::get('/get_payroll_employee', [\App\Http\Controllers\PayrollController::class, 'getPayrollEmployee'])->name('getPayrollEmployee');
    Route::get('/payroll/export/{payroll_id}', [\App\Http\Controllers\PayrollController::class, 'export'])->name('exportPayroll');
    Route::get('/payroll/payslip/{payroll_id}', [\App\Http\Controllers\PayrollController::class, 'payslip'])->name('payrollPayslip');
    Route::get('/payroll/summary/{payroll_id}', [\App\Http\Controllers\PayrollController::class, 'payrollSummary'])->name('payrollSummary');
//    Route::delete('/delete/dtr', [\App\Http\Controllers\DTRController::class, 'delete'])->name('deleteDtr');

    #EMPLOYEE
    Route::get('/employee/get', [\App\Http\Controllers\EmployeeController::class, 'getEmployeeDetails'])->name('getEmployeeDetails');
    Route::get('/employee/get/list', [\App\Http\Controllers\EmployeeController::class, 'getEmployee'])->name('getEmployee');
    Route::post('/employee/update', [\App\Http\Controllers\EmployeeController::class, 'insertUpdateEmployee'])->name('insertUpdateEmployee');
    Route::post('/employee/update/status', [\App\Http\Controllers\EmployeeController::class, 'updateEmployeeStatus'])->name('updateEmployeeStatus');
#ADDRESS
    Route::get('/address/get', [\App\Http\Controllers\AddressController::class, 'getAddress'])->name('getAddress');
    Route::post('/address/update', [\App\Http\Controllers\AddressController::class, 'insertUpdateAddress'])->name('insertUpdateAddress');
#FAMILY BACKGROUND
    Route::get('/family_background/get', [\App\Http\Controllers\FamilyBackgroundController::class, 'getFamilyBackground'])->name('getFamilyBackground');
    Route::post('/family_background/update', [\App\Http\Controllers\FamilyBackgroundController::class, 'insertUpdateFamilyBackground'])->name('insertUpdateFamilyBackground');
    Route::post('/children/update', [\App\Http\Controllers\FamilyBackgroundController::class, 'insertUpdateChildren'])->name('insertUpdateChildren');
    Route::delete('/children/delete', [\App\Http\Controllers\FamilyBackgroundController::class, 'deleteChildren'])->name('deleteChildren');
#EDUCATION
    Route::get('/education/get', [\App\Http\Controllers\EducationController::class, 'getEducation'])->name('getEducation');
    Route::post('/education/update', [\App\Http\Controllers\EducationController::class, 'insertUpdateEducation'])->name('insertUpdateEducation');
    Route::delete('/education/delete', [\App\Http\Controllers\EducationController::class, 'deleteEducation'])->name('deleteEducation');
#CIVIL SERVICE
    Route::get('/civil_service/get', [\App\Http\Controllers\CivilServiceController::class, 'getCivilService'])->name('getCivilService');
    Route::post('/civil_service/update', [\App\Http\Controllers\CivilServiceController::class, 'insertUpdateCivilService'])->name('insertUpdateCivilService');
    Route::delete('/civil_service/delete', [\App\Http\Controllers\CivilServiceController::class, 'deleteCivilService'])->name('deleteCivilService');
#WORK EXPERIENCES
    Route::get('/work_experience/get', [\App\Http\Controllers\WorkController::class, 'getWork'])->name('getWork');
    Route::post('/work_experience/update', [\App\Http\Controllers\WorkController::class, 'insertUpdateWork'])->name('insertUpdateWork');
    Route::delete('/work_experience/delete', [\App\Http\Controllers\WorkController::class, 'deleteWork'])->name('deleteWork');
#VOLUNTARY WORKS
    Route::get('/voluntary/get', [\App\Http\Controllers\VoluntaryController::class, 'getVoluntary'])->name('getVoluntary');
    Route::post('/voluntary/update', [\App\Http\Controllers\VoluntaryController::class, 'insertUpdateVoluntary'])->name('insertUpdateVoluntary');
    Route::delete('/voluntary/delete', [\App\Http\Controllers\VoluntaryController::class, 'deleteVoluntary'])->name('deleteVoluntary');
#LEARNING & DEVELOPMENT
    Route::get('/learning/get', [\App\Http\Controllers\LearningController::class, 'getLearning'])->name('getLearning');
    Route::post('/learning/update', [\App\Http\Controllers\LearningController::class, 'insertUpdateLearning'])->name('insertUpdateLearning');
    Route::delete('/learning/delete', [\App\Http\Controllers\LearningController::class, 'deleteLearning'])->name('deleteLearning');
#REFERENCES
    Route::get('/reference/get', [\App\Http\Controllers\ReferenceController::class, 'getReference'])->name('getReference');
    Route::post('/reference/update', [\App\Http\Controllers\ReferenceController::class, 'insertUpdateReference'])->name('insertUpdateReference');
    Route::delete('/reference/delete', [\App\Http\Controllers\ReferenceController::class, 'deleteReference'])->name('deleteReference');
#OTHER INFORMATION
    Route::get('/other_information/get', [\App\Http\Controllers\OtherInformationController::class, 'getOtherInformation'])->name('getOtherInformation');
    Route::post('/skill/update', [\App\Http\Controllers\OtherInformationController::class, 'insertUpdateSkill'])->name('insertUpdateSkill');
    Route::post('/recognition/update', [\App\Http\Controllers\OtherInformationController::class, 'insertUpdateRecognition'])->name('insertUpdateRecognition');
    Route::post('/membership/update', [\App\Http\Controllers\OtherInformationController::class, 'insertUpdateMembership'])->name('insertUpdateMembership');
    Route::delete('/skill/delete', [\App\Http\Controllers\OtherInformationController::class, 'deleteSkill'])->name('deleteSkill');
    Route::delete('/recognition/delete', [\App\Http\Controllers\OtherInformationController::class, 'deleteRecognition'])->name('deleteRecognition');
    Route::delete('/membership/delete', [\App\Http\Controllers\OtherInformationController::class, 'deleteMembership'])->name('deleteMembership');
#SURVEYS
    Route::get('/survey/get', [\App\Http\Controllers\SurveyController::class, 'getSurvey'])->name('getSurvey');
    Route::post('/survey/update', [\App\Http\Controllers\SurveyController::class, 'insertUpdateSurveys'])->name('insertUpdateSurvey');
#SERVICE RECORDS
    Route::get('/service_records/get', [\App\Http\Controllers\ServiceRecordsController::class, 'getServiceRecords'])->name('getServiceRecords');
    Route::post('/service_records/update', [\App\Http\Controllers\ServiceRecordsController::class, 'insertUpdateServiceRecords'])->name('insertUpdateServiceRecords');
    Route::delete('/service_records/delete', [\App\Http\Controllers\ServiceRecordsController::class, 'deleteServiceRecords'])->name('deleteServiceRecords');
#REPORTS
    Route::get('/reports/leave_bal', [\App\Http\Controllers\ReportController::class, 'leaveBalance'])->name('leaveBalance');
    Route::get('/reports/quarterly-leave-balance', [\App\Http\Controllers\ReportController::class, 'quarterlyLeaveBalance'])->name('report.quarterly_leave_balance')->middleware('permission:reports.quarterly_leave');
    Route::get('/reports/quarterly-leave-balance/data', [\App\Http\Controllers\ReportController::class, 'getQuarterlyLeaveBalance'])->name('report.quarterly_leave_balance.data');
    Route::get('/reports/quarterly-leave-balance/history', [\App\Http\Controllers\ReportController::class, 'getQuarterlyLeaveBalanceHistory'])->name('report.quarterly_leave_balance.history');
    Route::get('/reports/quarterly-leave-balance/snapshot/{id}', [\App\Http\Controllers\ReportController::class, 'getQuarterlyLeaveBalanceSnapshot'])->name('report.quarterly_leave_balance.snapshot');
    Route::get('/reports/quarterly-leave-balance/pdf/{id}', [\App\Http\Controllers\ReportController::class, 'printQuarterlyLeaveBalancePdf'])->name('report.quarterly_leave_balance.pdf');
#SALN
    Route::post('/insert_declarant', [\App\Http\Controllers\SalnController::class, 'updateDeclarant'])->name('updateDeclarant');
    Route::post('/insert_spouse', [\App\Http\Controllers\SalnController::class, 'updateSpouse'])->name('updateSpouse');
    Route::post('/insert_saln_children', [\App\Http\Controllers\SalnController::class, 'insertSalnChildren'])->name('insertSalnChildren');    Route::post('/insert_declarant', [\App\Http\Controllers\SalnController::class, 'updateDeclarant'])->name('updateDeclarant');
    Route::post('/insert_spouse', [\App\Http\Controllers\SalnController::class, 'updateSpouse'])->name('updateSpouse');
    Route::post('/insert_saln_children', [\App\Http\Controllers\SalnController::class, 'insertSalnChildren'])->name('insertSalnChildren');
    Route::post('/insert_real_properties', [\App\Http\Controllers\SalnController::class, 'insertRealProperties'])->name('insertRealProperties');
    Route::post('/insert_personal_properties', [\App\Http\Controllers\SalnController::class, 'insertPersonalProperties'])->name('insertPersonalProperties');
    Route::post('/insert_liabilities', [\App\Http\Controllers\SalnController::class, 'insertLiabilities'])->name('insertLiabilities');
    Route::post('/insert_business_interest', [\App\Http\Controllers\SalnController::class, 'insertBusinessInterest'])->name('insertBusinessInterest');
    Route::post('/insert_government_relative', [\App\Http\Controllers\SalnController::class, 'insertGovernmentRelative'])->name('insertGovernmentRelative');
    Route::post('/insert_marriages', [\App\Http\Controllers\SalnController::class, 'insertMarriages'])->name('insertMarriages');
    Route::post('/insert_spouse_real_properties', [\App\Http\Controllers\SalnController::class, 'insertSpouseRealProperties'])->name('insertSpouseRealProperties');
    Route::post('/insert_spouse_personal_properties', [\App\Http\Controllers\SalnController::class, 'insertSpousePersonalProperties'])->name('insertSpousePersonalProperties');
    Route::post('/insert_spouse_liabilities', [\App\Http\Controllers\SalnController::class, 'insertSpouseLiabilities'])->name('insertSpouseLiabilities');
    Route::post('/insert_spouse_business_interest', [\App\Http\Controllers\SalnController::class, 'insertSpouseBusinessInterest'])->name('insertSpouseBusinessInterest');
    Route::get('/get_declarant', [\App\Http\Controllers\SalnController::class, 'getDeclarant'])->name('getDeclarant');
    Route::get('/get_declarant_spouse', [\App\Http\Controllers\SalnController::class, 'getDeclarantSpouse'])->name('getDeclarantSpouse');
    Route::get('/get_saln_children', [\App\Http\Controllers\SalnController::class, 'getChildren'])->name('getChildren');
    Route::get('/get_real_properties', [\App\Http\Controllers\SalnController::class, 'getRealProperties'])->name('getRealProperties');
    Route::get('/get_personal_properties', [\App\Http\Controllers\SalnController::class, 'getPersonalProperties'])->name('getPersonalProperties');
    Route::get('/get_liabilities', [\App\Http\Controllers\SalnController::class, 'getLiabilities'])->name('getLiabilities');
    Route::get('/get_business_interest', [\App\Http\Controllers\SalnController::class, 'getBusinessInterest'])->name('getBusinessInterest');
    Route::get('/get_government_relative', [\App\Http\Controllers\SalnController::class, 'getGovernmentRelative'])->name('getGovernmentRelative');
    Route::get('/get_marriages', [\App\Http\Controllers\SalnController::class, 'getMarriages'])->name('getMarriages');
    Route::get('/get_spouse_real_properties', [\App\Http\Controllers\SalnController::class, 'getSpouseRealProperties'])->name('getSpouseRealProperties');
    Route::get('/get_spouse_personal_properties', [\App\Http\Controllers\SalnController::class, 'getSpousePersonalProperties'])->name('getSpousePersonalProperties');
    Route::get('/get_spouse_liabilities', [\App\Http\Controllers\SalnController::class, 'getSpouseLiabilities'])->name('getSpouseLiabilities');
    Route::get('/get_spouse_business_interest', [\App\Http\Controllers\SalnController::class, 'getSpouseBusinessInterest'])->name('getSpouseBusinessInterest');
    Route::delete('/delete_saln_children', [\App\Http\Controllers\SalnController::class, 'deleteSalnChildren'])->name('deleteSalnChildren');
    Route::delete('/delete_real_properties', [\App\Http\Controllers\SalnController::class, 'deleteRealProperties'])->name('deleteRealProperties');
    Route::delete('/delete_personal_properties', [\App\Http\Controllers\SalnController::class, 'deletePersonalProperties'])->name('deletePersonalProperties');
    Route::delete('/delete_liabilities', [\App\Http\Controllers\SalnController::class, 'deleteLiabilities'])->name('deleteLiabilities');
    Route::delete('/delete_business_interest', [\App\Http\Controllers\SalnController::class, 'deleteBusinessInterest'])->name('deleteBusinessInterest');
    Route::delete('/delete_government_relative', [\App\Http\Controllers\SalnController::class, 'deleteGovernmentRelative'])->name('deleteGovernmentRelative');
    Route::delete('/delete_marriage', [\App\Http\Controllers\SalnController::class, 'deleteMarriage'])->name('deleteMarriage');
    Route::delete('/delete_spouse_real_properties', [\App\Http\Controllers\SalnController::class, 'deleteSpouseRealProperties'])->name('deleteSpouseRealProperties');
    Route::delete('/delete_spouse_personal_properties', [\App\Http\Controllers\SalnController::class, 'deleteSpousePersonalProperties'])->name('deleteSpousePersonalProperties');
    Route::delete('/delete_spouse_liabilities', [\App\Http\Controllers\SalnController::class, 'deleteSpouseLiabilities'])->name('deleteSpouseLiabilities');
    Route::delete('/delete_spouse_business_interest', [\App\Http\Controllers\SalnController::class, 'deleteSpouseBusinessInterest'])->name('deleteSpouseBusinessInterest');
#PROMOTION WORK EXPERIENCE
    Route::post('/insert_position_applied', [\App\Http\Controllers\PromotionWorkController::class, 'store'])->name('insertPositionApplied');
    Route::post('/insert_related_work', [\App\Http\Controllers\PromotionWorkController::class, 'insertRelatedWork'])->name('insertRelatedWork');
    Route::get('/get_promotion_work', [\App\Http\Controllers\PromotionWorkController::class, 'show'])->name('getPromotionWork');
    Route::get('/get_related_work', [\App\Http\Controllers\PromotionWorkController::class, 'getRelatedWork'])->name('getRelatedWork');
    Route::delete('/delete_position_applied', [\App\Http\Controllers\PromotionWorkController::class, 'delete'])->name('deletePositionApplied');
#LEAVE CREDITS MANAGEMENT
    Route::post('/insert_leave_credits', [\App\Http\Controllers\LeaveCreditsController::class, 'insertUpdateCredits'])->name('insertUpdateCredits');
    Route::post('/bulk_update_credits', [\App\Http\Controllers\LeaveCreditsController::class, 'bulkUpdateCredits'])->name('bulkUpdateCredits');
    Route::get('/get_leave_credits', [\App\Http\Controllers\LeaveCreditsController::class, 'getEmployeeCredits'])->name('getEmployeeCredits');
    Route::get('/get_all_balances', [\App\Http\Controllers\LeaveCreditsController::class, 'getAllBalances'])->name('getAllBalances');
#LEAVE APPLICATION
    Route::get('/get_leaves', [\App\Http\Controllers\LeaveController::class, 'getLeaves'])->name('getLeaves');
    Route::get('/get_leave_status', [\App\Http\Controllers\LeaveController::class, 'getLeaveStatus'])->name('getLeaveStatus');
    Route::post('/insert_leave', [\App\Http\Controllers\LeaveController::class, 'insertLeaves'])->name('insertLeaves');
#SETTINGS
    Route::post('/password/update', [\App\Http\Controllers\UserController::class, 'changePassword'])->name('changePassword');
#NEW LEAVE APPLICATION
    Route::get('/get_leave_applications', [\App\Http\Controllers\LeaveApplicationController::class, 'index'])->name('getLeaveApplications');
    Route::post('/insert_leave_applications', [\App\Http\Controllers\LeaveApplicationController::class, 'insertLeaveApplication'])->name('insertLeaveApplication');

    Route::get('/leave-applications/{id}', [\App\Http\Controllers\LeaveApplicationController::class, 'getLeaveApplication'])->name('leave-applications.show');
    Route::put('/leave-applications/{id}', [\App\Http\Controllers\LeaveApplicationController::class, 'updateLeaveApplication'])->name('leave-applications.update');
    Route::delete('/leave-applications/{id}', [\App\Http\Controllers\LeaveApplicationController::class, 'delete'])->name('leave-applications.destroy');
    Route::post('/leave-applications/{id}/attachments', [\App\Http\Controllers\LeaveApplicationController::class, 'addAttachments'])->name('leave-applications.attachments.add');
    Route::delete('/leave-application-attachments/{id}', [\App\Http\Controllers\LeaveApplicationController::class, 'deleteAttachment'])->name('leave-applications.attachments.delete');
    Route::get('/leave-attachments/{filename}', [\App\Http\Controllers\LeaveApplicationController::class, 'serveAttachment'])->name('leave-attachments.serve');


#LEAVE MONETIZATION
    Route::get('/monetizable-leave-types', [\App\Http\Controllers\LeaveMonetizationController::class, 'monetizableTypes'])->name('monetizableLeaveTypes');
    Route::get('/leave-monetizations', [\App\Http\Controllers\LeaveMonetizationController::class, 'index'])->name('getLeaveMonetizations');
    Route::post('/leave-monetizations', [\App\Http\Controllers\LeaveMonetizationController::class, 'store'])->name('storeLeaveMonetization');
    Route::delete('/leave-monetizations/{id}', [\App\Http\Controllers\LeaveMonetizationController::class, 'delete'])->name('leave-monetizations.destroy');
    Route::get('/get-monetization-approvals', [\App\Http\Controllers\LeaveMonetizationApprovalController::class, 'getForApproval'])->name('getMonetizationApprovals');
    Route::post('/update-monetization-approval-status', [\App\Http\Controllers\LeaveMonetizationApprovalController::class, 'updateApprovalStatus'])->name('updateMonetizationApprovalStatus');

#LEAVE APPROVAL
    Route::get('/get_leave_approval', [\App\Http\Controllers\LeaveApplicationController::class, 'getLeaveForApproval'])->name('getLeaveForApproval');
    Route::post('/update/approval_status', [\App\Http\Controllers\LeaveApplicationController::class, 'updateApprovalStatus'])->name('updateApprovalStatus');
    Route::post('/undo_approval', [\App\Http\Controllers\LeaveApplicationController::class, 'undoApproval'])->name('undoApproval');

#LEAVE CANCELLATION (Pages)
    Route::get('/leave_cancellation', function () { return view('leave_cancellation'); })->name('leave_cancellation');
    Route::get('/cancellation_approval', function () { return view('cancellation_approval'); })->name('cancellation_approval');
    Route::get('/admin_cancellations', function () { return view('admin_cancellations'); })->name('admin_cancellations');

#LEAVE CANCELLATION (API)
    Route::get('/get_approved_dates', [\App\Http\Controllers\LeaveCancellationController::class, 'getApprovedDates'])->name('getApprovedDates');
    Route::post('/file_leave_cancellation', [\App\Http\Controllers\LeaveCancellationController::class, 'store'])->name('fileLeaveCancellation');
    Route::get('/my_cancellations', [\App\Http\Controllers\LeaveCancellationController::class, 'getMyCancellations'])->name('getMyCancellations');
    Route::get('/leave-cancellations/{id}', [\App\Http\Controllers\LeaveCancellationController::class, 'show'])->name('leave-cancellations.show');
    Route::get('/get_cancellation_for_approval', [\App\Http\Controllers\LeaveCancellationController::class, 'getForApproval'])->name('getCancellationForApproval');
    Route::post('/update_cancellation_approval_status', [\App\Http\Controllers\LeaveCancellationController::class, 'updateApprovalStatus'])->name('updateCancellationApprovalStatus');
    Route::get('/get_all_cancellations_admin', [\App\Http\Controllers\LeaveCancellationController::class, 'getAllForAdmin'])->name('getAllCancellationsAdmin');

#DTR LOGS
    Route::get('/print_dtr', [\App\Http\Controllers\DTRController::class, 'printDTR'])->name('printDTR')->middleware('permission:dtr.print');
    Route::get('/get_employee_dtr', [\App\Http\Controllers\DTRController::class, 'getEmployeeDtr'])->name('getEmployeeDtr');
    Route::get('/get_division_list', [\App\Http\Controllers\DTRController::class, 'getDivisionList'])->name('getDivisionList');
    Route::get('/get_section_list', [\App\Http\Controllers\DTRController::class, 'getSectionList'])->name('getSectionList');
    Route::post('/save/dtr_logs', [\App\Http\Controllers\DTRController::class, 'storeManual'])->name('storeManual');
    Route::delete('/delete/dtr_logs', [\App\Http\Controllers\DTRController::class, 'deleteTimeLog'])->name('deleteTimeLog');

#SCHEDULE MASTERLIST
    Route::get('/get_schedule_master_lists', [\App\Http\Controllers\ScheduleController::class, 'getScheduleMasterList'])->name('getScheduleMasterList');
    Route::post('/save_schedule_master_list', [\App\Http\Controllers\ScheduleController::class, 'saveScheduleMasterList'])->name('saveScheduleMasterList');
    Route::delete('/delete_schedule_master_list/{id}', [\App\Http\Controllers\ScheduleController::class, 'deleteScheduleMasterList'])->name('deleteScheduleMasterList');
#SCHEDULE
    Route::get('/get_employee_schedules', [\App\Http\Controllers\ScheduleController::class, 'getSchedules'])->name('getSchedules');
    Route::get('/schedule/print', [\App\Http\Controllers\ScheduleController::class, 'printSchedule'])->name('printSchedule');
    Route::post('/insert_schedule', [\App\Http\Controllers\ScheduleController::class, 'setEmployeeSchedule'])->name('setEmployeeSchedule');
    Route::post('/save-advance-schedule', [\App\Http\Controllers\ScheduleController::class, 'saveAdvanceSchedule'])->name('saveAdvanceSchedule');
    Route::get('/schedule/managed-sections', [\App\Http\Controllers\ScheduleController::class, 'getManagedSections'])->name('getManagedSections');
    Route::get('/schedule/manager-assignments', [\App\Http\Controllers\ScheduleController::class, 'getScheduleManagerAssignments'])->name('getScheduleManagerAssignments');
    Route::post('/schedule/manager-assignments', [\App\Http\Controllers\ScheduleController::class, 'saveScheduleManagerSections'])->name('saveScheduleManagerSections');

#CREDIT EARNED POINTS
    Route::get('/get_credit_earned_logs', [\App\Http\Controllers\CreditController::class, 'getCreditEarned'])->name('getCreditEarned');
    #SETTINGS
    Route::get('/calendar-events', [CalendarController::class, 'getHolidays'])->name('calendar.events');
    Route::post('/add-holiday', [CalendarController::class, 'store'])->name('calendar.store');
    Route::post('/update-holiday', [CalendarController::class, 'update'])->name('calendar.update');
    Route::post('/delete-holiday', [CalendarController::class, 'destroy'])->name('calendar.delete');

    Route::get('/managed-employees', [\App\Http\Controllers\ManagedEmployeeController::class, 'index'])->name('managed-employees.index');
#HOSPITAL ORDERS
    Route::get('/get_hospital_orders', [HospitalOrderController::class, 'getHospitalOrders'])->name('hospital-orders.get');
    Route::post('/hospital-orders', [HospitalOrderController::class, 'createHospitalOrder'])->name('hospital-orders.store');
    Route::post('/hospital-orders/file', [HospitalOrderController::class, 'fileForSelf'])->name('hospital-orders.file');
    Route::get('/hospital-orders/my-filings', [HospitalOrderController::class, 'getMyFilings'])->name('hospital-orders.my-filings');
    Route::get('/hospital-orders/filings-for-review', [HospitalOrderController::class, 'getFilingsForReview'])->name('hospital-orders.filings-for-review');
    Route::post('/hospital-orders/filings/{id}/approve', [HospitalOrderController::class, 'approveFiling'])->name('hospital-orders.filings.approve');
    Route::post('/hospital-orders/filings/{id}/reject', [HospitalOrderController::class, 'rejectFiling'])->name('hospital-orders.filings.reject');
    Route::delete('/hospital-orders/filings/{id}', [HospitalOrderController::class, 'deleteMyFiling'])->name('hospital-orders.filings.delete');

#OVERTIME REQUESTS
    Route::post('/overtime/file', [OvertimeController::class, 'fileForSelf'])->name('overtime.file');
    Route::get('/overtime/my-filings', [OvertimeController::class, 'getMyFilings'])->name('overtime.my-filings');
    Route::get('/overtime/filings-for-review', [OvertimeController::class, 'getFilingsForReview'])->name('overtime.filings-for-review');
    Route::post('/overtime/filings/{id}/approve', [OvertimeController::class, 'approveFiling'])->name('overtime.filings.approve');
    Route::post('/overtime/filings/{id}/reject', [OvertimeController::class, 'rejectFiling'])->name('overtime.filings.reject');
    Route::delete('/overtime/filings/{id}', [OvertimeController::class, 'deleteMyFiling'])->name('overtime.filings.delete');

#PRINTING LEAVE FORMS
    Route::get('/print_leave', [\App\Http\Controllers\PrintController::class, 'printLeaveForm'])->name('printLeaveForm');

#PASS SLIP
    Route::get('/pass-slip', [PassSlipController::class, 'index'])->name('pass-slip.index');
    Route::get('/pass-slip/list', [PassSlipController::class, 'list'])->name('pass-slip.list');
    Route::post('/pass-slip/store', [PassSlipController::class, 'store'])->name('pass-slip.store');
    Route::delete('/pass-slip/{id}', [PassSlipController::class, 'destroy'])->name('pass-slip.destroy');


    // Pass Slip Approver Routes
    Route::prefix('pass-slip-approver')->group(function () {
        Route::get('/', [PassSlipApproverController::class, 'index'])->name('pass-slip.approver.index')->middleware('permission:passslip.approve');
        Route::get('/list', [PassSlipApproverController::class, 'list'])->name('pass-slip.approver.list');
        Route::post('/approve/{id}', [PassSlipApproverController::class, 'approve'])->name('pass-slip.approver.approve');
        Route::post('/reject/{id}', [PassSlipApproverController::class, 'reject'])->name('pass-slip.approver.reject');
    });

    // SALN Historical Tracking Routes
    Route::post('/update_saln_compliance', [\App\Http\Controllers\SalnController::class, 'updateSalnCompliance'])->name('updateSalnCompliance');
    Route::get('/get_saln_years', [\App\Http\Controllers\SalnController::class, 'getSalnYears'])->name('getSalnYears');
    Route::get('/get_saln_by_year', [\App\Http\Controllers\SalnController::class, 'getSalnByYear'])->name('getSalnByYear');
    Route::get('/generate_yearly_saln_report', [\App\Http\Controllers\SalnController::class, 'generateYearlySalnReport'])->name('generateYearlySalnReport');
    Route::delete('/delete_saln_record', [\App\Http\Controllers\SalnController::class, 'deleteSalnRecord'])->name('deleteSalnRecord');
    Route::post('/clone_latest_saln', [\App\Http\Controllers\SalnController::class, 'cloneLatestSalnData'])->name('cloneLatestSaln');

    // SALN Admin Routes
    Route::prefix('saln-admin')->group(function () {
        Route::get('/', [\App\Http\Controllers\SalnAdminController::class, 'index'])->name('saln.admin.index')->middleware('permission:saln.admin');
        Route::get('/employees', [\App\Http\Controllers\SalnAdminController::class, 'getEmployees'])->name('saln.admin.employees');
        Route::get('/history/{user_id}', [\App\Http\Controllers\SalnAdminController::class, 'getEmployeeSalnHistory'])->name('saln.admin.history');
        Route::post('/update-filing-type', [\App\Http\Controllers\SalnAdminController::class, 'updateFilingType'])->name('saln.admin.updateFilingType');
        Route::get('/summary-report', [\App\Http\Controllers\SalnAdminController::class, 'generateSummaryReport'])->name('saln.admin.summaryReport');
    });

    # ADMIN — Access Rights Management
    Route::get('/admin/permissions', [\App\Http\Controllers\Admin\PermissionController::class, 'index'])
        ->name('admin.permissions');
    Route::get('/admin/permissions/users', [\App\Http\Controllers\Admin\PermissionController::class, 'users'])
        ->name('admin.permissions.users');
    Route::get('/admin/permissions/users/{id}', [\App\Http\Controllers\Admin\PermissionController::class, 'show'])
        ->name('admin.permissions.users.show');
    Route::post('/admin/permissions/users/{id}', [\App\Http\Controllers\Admin\PermissionController::class, 'update'])
        ->name('admin.permissions.users.update');
    Route::delete('/admin/permissions/users/{id}/reset', [\App\Http\Controllers\Admin\PermissionController::class, 'reset'])
        ->name('admin.permissions.users.reset');

    Route::get('/admin/permission-levels', [\App\Http\Controllers\Admin\PermissionLevelController::class, 'index'])
        ->name('admin.permission_levels');
    Route::get('/admin/permission-levels/data', [\App\Http\Controllers\Admin\PermissionLevelController::class, 'data'])
        ->name('admin.permission_levels.data');
    Route::post('/admin/permission-levels/update', [\App\Http\Controllers\Admin\PermissionLevelController::class, 'update'])
        ->name('admin.permission_levels.update');

    // Re-Orientation
    Route::get('/reorientation', [\App\Http\Controllers\ReorientationController::class, 'index'])->name('reorientation');
    Route::get('/reorientation/my-modules', [\App\Http\Controllers\ReorientationController::class, 'getMyModules'])->name('reorientation.my_modules');
    Route::get('/reorientation/my-post-test', [\App\Http\Controllers\ReorientationController::class, 'getMyPostTest'])->name('reorientation.my_post_test');
    Route::get('/reorientation/module/{moduleNo}', [\App\Http\Controllers\ReorientationController::class, 'readModule'])
        ->name('reorientation.read')->whereNumber('moduleNo');
    Route::get('/reorientation/module-pdf/{moduleNo}',[\App\Http\Controllers\ReorientationController::class, 'modulePdf'])
        ->name('reorientation.module_pdf')->whereNumber('moduleNo');
    Route::post('/reorientation/submit',[\App\Http\Controllers\ReorientationController::class, 'submitPostTest'])->name('reorientation.submit');

    Route::get('/reorientation/questions', [\App\Http\Controllers\ReorientationController::class, 'getQuestions'])
        ->name('reorientation.questions')->middleware('permission:reorientation.manage');
    Route::post('/reorientation/questions', [\App\Http\Controllers\ReorientationController::class, 'saveQuestion'])
        ->name('reorientation.questions.save')->middleware('permission:reorientation.manage');
    Route::delete('/reorientation/questions/{id}', [\App\Http\Controllers\ReorientationController::class, 'deleteQuestion'])
        ->name('reorientation.questions.delete')->middleware('permission:reorientation.manage');
    Route::post('/reorientation/questions/{id}/move', [\App\Http\Controllers\ReorientationController::class, 'moveQuestion'])
        ->name('reorientation.questions.move')->middleware('permission:reorientation.manage');

    Route::get('/reorientation/employee-modules', [\App\Http\Controllers\ReorientationController::class, 'getEmployeeModules'])
        ->name('reorientation.employee_modules')->middleware('permission:reorientation.check');
    Route::get('/reorientation/employee-submission', [\App\Http\Controllers\ReorientationController::class, 'getEmployeeSubmission'])
        ->name('reorientation.employee_submission')->middleware('permission:reorientation.check');
    Route::post('/reorientation/check', [\App\Http\Controllers\ReorientationController::class, 'checkSubmission'])
        ->name('reorientation.check')->middleware('permission:reorientation.check');
});

    // SALN List View Example
    Route::get('/saln-list-example', function () {
        return view('examples.saln_list_modal_example');
    })->name('salnListExample');
