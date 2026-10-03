<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/
use Illuminate\Support\Facades\Mail;

Route::get('/test-mail', function () {

    Mail::raw('Test email from Laravel + Mailgun', function ($message) {
        $message
            ->to('tkouleris@gmail.com')
            ->subject('Laravel Mailgun Test');
    });

    return 'Email sent!';
});

Route::get('/', function () {
    return view('welcome');
});

// Everything in this group requires a logged-in user with a verified email.
// Unverified users are redirected to the verification notice page.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('/home', 'home')->name('home');
});

require __DIR__ . '/auth.php';
