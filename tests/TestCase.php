<?php

namespace ArslanAamir\DevMailRedirect\Tests;

use ArslanAamir\DevMailRedirect\DevMailRedirectServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [DevMailRedirectServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('mail.default', 'array');
        $app['config']->set('mail.from', ['address' => 'app@example.com', 'name' => 'App']);
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('logging.default', 'null');
        $app['config']->set('dev-mail-redirect.enabled', true);
        $app['config']->set('dev-mail-redirect.to', 'dev@example.test');
    }
}
