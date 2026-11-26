<?php

namespace ArslanAamir\DevMailRedirect;

use ArslanAamir\DevMailRedirect\Listeners\RedirectOutgoingMail;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\ServiceProvider;

class DevMailRedirectServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/dev-mail-redirect.php', 'dev-mail-redirect');

        // Registered here rather than in boot() so this listener runs before the
        // application's own listeners. Laravel stops at the first MessageSending
        // listener that returns a non-null value.
        $this->app['events']->listen(MessageSending::class, RedirectOutgoingMail::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/dev-mail-redirect.php' => $this->app->configPath('dev-mail-redirect.php'),
            ], 'dev-mail-redirect-config');
        }
    }
}
