<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\ChatHistory;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| LIVE CURRENCY UPDATER (Har 1 Ghante me)
|--------------------------------------------------------------------------
| Ye job har ghante background me chalegi aur Google se live rate
| nikal kar Cache memory me daal degi.
*/
Schedule::command('app:update-exchange-rate')->hourly();

/*
|--------------------------------------------------------------------------
| CHATBOT AUTOMATION (Midnight Cleanup)
|--------------------------------------------------------------------------
| Ye code roz raat ko 12:00 AM (Indian Time) chalega aur 
| purani chat history delete kar dega.
*/
Schedule::call(function () {
    ChatHistory::truncate();
})->dailyAt('00:00')->timezone('Asia/Kolkata');