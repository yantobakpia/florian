<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class RecordUserLogin
{
    public function handle(Login $event): void
    {
        $event->user->recordLogin();
    }
}