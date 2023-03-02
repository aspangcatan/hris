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
    return view('profile');
});

Route::get('/logout', function () {
    return view('home');
})->name('logout');

#EMPLOYEE
Route::get('/employee/get',[\App\Http\Controllers\EmployeeController::class,'getEmployeeDetails'])->name('getEmployeeDetails');
Route::post('/employee/update',[\App\Http\Controllers\EmployeeController::class,'insertUpdateEmployee'])->name('insertUpdateEmployee');
#ADDRESS
Route::get('/address/get',[\App\Http\Controllers\AddressController::class,'getAddress'])->name('getAddress');
Route::post('/address/update',[\App\Http\Controllers\AddressController::class,'insertUpdateAddress'])->name('insertUpdateAddress');
#FAMILY BACKGROUND
Route::get('/family_background/get',[\App\Http\Controllers\FamilyBackgroundController::class,'getFamilyBackground'])->name('getFamilyBackground');
Route::post('/family_background/update',[\App\Http\Controllers\FamilyBackgroundController::class,'insertUpdateFamilyBackground'])->name('insertUpdateFamilyBackground');
Route::post('/children/update',[\App\Http\Controllers\FamilyBackgroundController::class,'insertUpdateChildren'])->name('insertUpdateChildren');
Route::delete('/children/delete',[\App\Http\Controllers\FamilyBackgroundController::class,'deleteChildren'])->name('deleteChildren');

#EDUCATION
Route::get('/education/get',[\App\Http\Controllers\EducationController::class,'getEducation'])->name('getEducation');
Route::post('/education/update',[\App\Http\Controllers\EducationController::class,'insertUpdateEducation'])->name('insertUpdateEducation');
#CIVIL SERVICE
Route::get('/civil_service/get',[\App\Http\Controllers\CivilServiceController::class,'getCivilService'])->name('getCivilService');
Route::post('/civil_service/update',[\App\Http\Controllers\CivilServiceController::class,'insertUpdateCivilService'])->name('insertUpdateCivilService');
Route::delete('/civil_service/delete',[\App\Http\Controllers\CivilServiceController::class,'deleteCivilService'])->name('deleteCivilService');
#WORK EXPERIENCES
Route::get('/work_experience/get',[\App\Http\Controllers\WorkController::class,'getWork'])->name('getWork');
Route::post('/work_experience/update',[\App\Http\Controllers\WorkController::class,'insertUpdateWork'])->name('insertUpdateWork');
Route::delete('/work_experience/delete',[\App\Http\Controllers\WorkController::class,'deleteWork'])->name('deleteWork');
#VOLUNTARY WORKS
Route::get('/voluntary/get',[\App\Http\Controllers\VoluntaryController::class,'getVoluntary'])->name('getVoluntary');
Route::post('/voluntary/update',[\App\Http\Controllers\VoluntaryController::class,'insertUpdateVoluntary'])->name('insertUpdateVoluntary');
Route::delete('/voluntary/delete',[\App\Http\Controllers\VoluntaryController::class,'deleteVoluntary'])->name('deleteVoluntary');
#LEARNING & DEVELOPMENT
Route::get('/learning/get',[\App\Http\Controllers\LearningController::class,'getLearning'])->name('getLearning');
Route::post('/learning/update',[\App\Http\Controllers\LearningController::class,'insertUpdateLearning'])->name('insertUpdateLearning');
Route::delete('/learning/delete',[\App\Http\Controllers\LearningController::class,'deleteLearning'])->name('deleteLearning');
#REFERENCES
Route::get('/reference/get',[\App\Http\Controllers\ReferenceController::class,'getReference'])->name('getReference');
Route::post('/reference/update',[\App\Http\Controllers\ReferenceController::class,'insertUpdateReference'])->name('insertUpdateReference');
Route::delete('/reference/delete',[\App\Http\Controllers\ReferenceController::class,'deleteReference'])->name('deleteReference');
