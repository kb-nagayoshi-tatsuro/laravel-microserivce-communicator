<?php

namespace MadMountainIo\MicroserviceCommunicator;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\ServiceProvider;

class MicroserviceCommunicationServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/microservice-communication.php',
            'microservice-communication'
        );

        $this->app->singleton(MicroserviceCommunicationManager::class, function ($app) {
            $config = $app['config']['microservice-communication'];

            if ($config['default'] === 'redis') {
                // Add Redis connection to config
                $config['connections']['redis']['redis_connection'] =
                    Redis::connection($config['connections']['redis']['connection'] ?? 'default');
            }

            return new MicroserviceCommunicationManager(
                $config['default'],
                $config['connections'][$config['default']]
            );
        });
    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/microservice-communication.php' => config_path('microservice-communication.php'),
        ], 'config');
    }

}