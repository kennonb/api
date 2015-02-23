<?php

namespace Dingo\Api\Provider;

use Dingo\Api\Properties;
use Illuminate\Support\ServiceProvider;

class PropertiesServiceProvider extends ServiceProvider
{
    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes( [
            __DIR__ . '/../config/config.php' => config_path( 'dingo.php' )
        ] );

        $this->mergeConfigFrom(
            __DIR__ . '/../config/config.php', 'dingo'
        );
    }

    /**
     * Register bindings for the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bindShared('api.properties', function ($app) {
            $properties = $app['config']['dingo'];

            return new Properties(
                $properties['version'],
                $properties['prefix'],
                $properties['domain'],
                $properties['vendor'],
                $properties['default_format']
            );
        });
    }
}
