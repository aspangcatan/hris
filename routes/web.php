<?php

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

Route::group(['middleware' => 'auth'], function () {
    #PRINTING
    Route::get('/pds', [\App\Http\Controllers\PrintController::class, 'printPds'])->name('printPds');
    Route::get('/service_records', [\App\Http\Controllers\PrintController::class, 'printServiceRecord'])->name('printServiceRecord');
    Route::get('/print_saln', [\App\Http\Controllers\PrintController::class, 'printSaln'])->name('printSaln');
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
    })->name('saln');

    Route::get('/employee', function () {
        return view('employee');
    })->name('employee');

    Route::get('/leave', function () {
        return view('leave');
    })->name('leave');

    Route::get('/promotion_applied', function () {
        return view('promotion_work_applied');
    })->name('promotion_applied');

    Route::get('/leave_management', function () {
        return view('leave_management');
    })->name('leave_management');

    Route::get('/review_leave', function () {
        return view('review_leave');
    })->name('review_leave');

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
#SALN
    Route::post('/insert_declarant', [\App\Http\Controllers\SalnController::class, 'updateDeclarant'])->name('updateDeclarant');
    Route::post('/insert_saln_children', [\App\Http\Controllers\SalnController::class, 'insertSalnChildren'])->name('insertSalnChildren');
    Route::post('/insert_real_properties', [\App\Http\Controllers\SalnController::class, 'insertRealProperties'])->name('insertRealProperties');
    Route::post('/insert_personal_properties', [\App\Http\Controllers\SalnController::class, 'insertPersonalProperties'])->name('insertPersonalProperties');
    Route::post('/insert_liabilities', [\App\Http\Controllers\SalnController::class, 'insertLiabilities'])->name('insertLiabilities');
    Route::post('/insert_business_interest', [\App\Http\Controllers\SalnController::class, 'insertBusinessInterest'])->name('insertBusinessInterest');
    Route::post('/insert_government_relative', [\App\Http\Controllers\SalnController::class, 'insertGovernmentRelative'])->name('insertGovernmentRelative');
    Route::get('/get_declarant', [\App\Http\Controllers\SalnController::class, 'getDeclarant'])->name('getDeclarant');
    Route::get('/get_declarant_spouse', [\App\Http\Controllers\SalnController::class, 'getDeclarantSpouse'])->name('getDeclarantSpouse');
    Route::get('/get_saln_children', [\App\Http\Controllers\SalnController::class, 'getChildren'])->name('getChildren');
    Route::get('/get_real_properties', [\App\Http\Controllers\SalnController::class, 'getRealProperties'])->name('getRealProperties');
    Route::get('/get_personal_properties', [\App\Http\Controllers\SalnController::class, 'getPersonalProperties'])->name('getPersonalProperties');
    Route::get('/get_liabilities', [\App\Http\Controllers\SalnController::class, 'getLiabilities'])->name('getLiabilities');
    Route::get('/get_business_interest', [\App\Http\Controllers\SalnController::class, 'getBusinessInterest'])->name('getBusinessInterest');
    Route::get('/get_government_relative', [\App\Http\Controllers\SalnController::class, 'getGovernmentRelative'])->name('getGovernmentRelative');
    Route::delete('/delete_saln_children', [\App\Http\Controllers\SalnController::class, 'deleteSalnChildren'])->name('deleteSalnChildren');
    Route::delete('/delete_real_properties', [\App\Http\Controllers\SalnController::class, 'deleteRealProperties'])->name('deleteRealProperties');
    Route::delete('/delete_personal_properties', [\App\Http\Controllers\SalnController::class, 'deletePersonalProperties'])->name('deletePersonalProperties');
    Route::delete('/delete_liabilities', [\App\Http\Controllers\SalnController::class, 'deleteLiabilities'])->name('deleteLiabilities');
    Route::delete('/delete_business_interest', [\App\Http\Controllers\SalnController::class, 'deleteBusinessInterest'])->name('deleteBusinessInterest');
    Route::delete('/delete_government_relative', [\App\Http\Controllers\SalnController::class, 'deleteGovernmentRelative'])->name('deleteGovernmentRelative');
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

});
