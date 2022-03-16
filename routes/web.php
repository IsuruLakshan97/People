<?php

use Illuminate\Support\Facades\Auth;
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
    return view('welcome');
});

Auth::routes(['register' => false]);

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

//forum routes
Route::get('/forum', [App\Http\Controllers\ForumController::class, 'index'])->name('forum.home');
Route::get('/forum/student', [App\Http\Controllers\ForumController::class, 'studentForum']);
Route::get('/forum/staff', [App\Http\Controllers\ForumController::class, 'staffForum']);

//people routes
Route::get('/people', [App\Http\Controllers\PeopleController::class, 'index'])->name('people.home');
Route::get('/people/student', [App\Http\Controllers\PeopleController::class, 'getStudent'])->name('people.student');
Route::get('/people/academic', [App\Http\Controllers\PeopleController::class, 'getAcademic'])->name('people.academic');
