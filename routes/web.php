<?php

use App\Http\Controllers\ReminderController;
use App\Http\Controllers\ThemeController;
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

// Unverified users can switch themes too, so this only needs a login.
Route::put('/theme', [ThemeController::class, 'update'])->middleware('auth')->name('theme.update');

// Everything in this group requires a logged-in user with a verified email.
// Unverified users are redirected to the verification notice page.
Route::middleware(['auth', 'verified'])->group(function () {
    // Old entry point; the notification list is now the home page.
    Route::redirect('/home', '/notifications')->name('home');

    Route::resource('notifications', ReminderController::class)
        ->except('show')
        ->parameters(['notifications' => 'reminder'])
        ->names('reminders');
});

require __DIR__ . '/auth.php';
