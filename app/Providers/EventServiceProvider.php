<?php

namespace App\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
// use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
     public function boot()
    {
        parent::boot();

        \Illuminate\Support\Facades\Event::listen(Login::class, function (Login $event): void {
            activity_log(
                'login',
                'Auth',
                $event->user->id ?? null,
                'User logged in'
            );
        });

        \Illuminate\Support\Facades\Event::listen(Logout::class, function (Logout $event): void {
            activity_log(
                'logout',
                'Auth',
                $event->user->id ?? null,
                'User logged out'
            );
        });
    }
}
