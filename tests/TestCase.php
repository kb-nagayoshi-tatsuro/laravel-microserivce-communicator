<?php

namespace MadMountainIo\MicroserviceCommunicator\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use MadMountainIo\MicroserviceCommunicator\MicroserviceCommunicationServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            MicroserviceCommunicationServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('microservice-communication.default', 'azure');
        $app['config']->set('microservice-communication.connections.azure', [
            'endpoint' => 'https://test-endpoint.servicebus.windows.net',
            'shared_access_key_name' => 'test-key-name',
            'shared_access_key' => 'test-key',
        ]);
        $app['config']->set('microservice-communication.connections.redis', [
            'connection' => 'default',
            'group_name' => 'test-group',
        ]);
    }
}