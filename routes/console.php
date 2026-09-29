<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Parliament data
|--------------------------------------------------------------------------
|
| New Votes and Proceedings / Minutes are published after each sitting
| day, and proofs are occasionally corrected, so the sync looks back over
| recent documents daily. Unchanged documents are skipped cheaply.
|
*/

Schedule::command('vic:sync-proceedings --since=-21days')
    ->dailyAt('06:00')
    ->timezone('Australia/Melbourne')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('vic:audit')
    ->dailyAt('06:30')
    ->timezone('Australia/Melbourne')
    ->withoutOverlapping()
    ->onOneServer();
