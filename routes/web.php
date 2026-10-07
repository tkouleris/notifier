<?php

use App\Http\Controllers\BirthdayController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\SettingsController;
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

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');

    // Birthdays are listed and deleted with the other notifications; only their form differs.
    Route::resource('notifications/birthdays', BirthdayController::class)
        ->only(['create', 'store', 'edit', 'update'])
        ->parameters(['birthdays' => 'reminder'])
        ->names('birthdays');

    Route::resource('notifications', ReminderController::class)
        ->except('show')
        ->parameters(['notifications' => 'reminder'])
        ->names('reminders');
});

require __DIR__ . '/auth.php';
