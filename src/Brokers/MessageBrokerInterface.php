<?php

namespace MadMountainIo\MicroserviceCommunicator\Brokers;

interface MessageBrokerInterface
{
    public function publish(string $queueName, array $message): bool;
    public function subscribe(string $queueName, callable $callback): void;
}