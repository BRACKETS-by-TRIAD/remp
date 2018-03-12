<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use DeviceDetector\DeviceDetector;
use DeviceDetector\Parser\Device\Mobile;

class DeviceDetectorServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [DeviceDetector::class, Mobile::class];
    }
}
