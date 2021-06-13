<?php

namespace Vidavh\Gateway;

use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap the application services.
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register the application services.
     * @return void
     */
    public function register()
    {
//        $this->mergeConfigFrom(dirname(__DIR__,1).'../config/gateway.php' , 'gateway');
        $this->app->singleton('gateway', function () {
            return new GatewayResolver();
        });
    }

}
