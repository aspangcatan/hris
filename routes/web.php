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
Route::get('/family_background/get',[\App\Http\Controllers\AddressController::class,'getAddress'])->name('getFamilyBackground');
Route::post('/family_background/update',[\App\Http\Controllers\AddressController::class,'insertUpdateAddress'])->name('insertUpdateFamilyBackground');
