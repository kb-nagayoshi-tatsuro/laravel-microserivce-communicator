<?php

namespace MadMountainIo\MicroserviceCommunicator;

use MadMountainIo\MicroserviceCommunicator\Brokers\MessageBrokerInterface;
use MadMountainIo\MicroserviceCommunicator\Contracts\AzureServiceBusBroker;
use MadMountainIo\MicroserviceCommunicator\Contracts\RedisStreamBroker;

class MicroserviceCommunicationManager
{
    private MessageBrokerInterface $broker;

    public function __construct(string $driver, array $config)
    {
        $this->broker = match ($driver) {
            'azure' => new AzureServiceBusBroker($config),
            'redis' => new RedisStreamBroker($config['redis_connection'], $config),
            default => throw new \InvalidArgumentException("Unsupported driver: {$driver}")
        };
    }

    public function publish(string $topic, array $message): bool
    {
        return $this->broker->publish($topic, $message);
    }

    public function subscribe(string $topic, callable $callback): void
    {
        $this->broker->subscribe($topic, $callback);
    }
}