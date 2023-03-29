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

Route::group(['middleware' => 'auth'], function () {
    #PRINTING
    Route::get('/pds', [\App\Http\Controllers\PrintController::class, 'printPds'])->name('printPds');
    Route::get('/service_records', [\App\Http\Controllers\PrintController::class, 'printServiceRecord'])->name('printServiceRecord');

    #NAVIGATION
    Route::get('/profile', function () {
        return view('profile');
    })->name('profile');

    Route::get('/employee', function () {
        return view('employee');
    })->name('employee');

    Route::get('/leave', function () {
        return view('leave');
    })->name('leave');

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
});
