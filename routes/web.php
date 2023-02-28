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

Route::get('/employee/get',[\App\Http\Controllers\EmployeeController::class,'getEmployeeDetails'])->name('getEmployeeDetails');
Route::post('/employee/update',[\App\Http\Controllers\EmployeeController::class,'insertUpdateEmployee'])->name('insertUpdateEmployee');
