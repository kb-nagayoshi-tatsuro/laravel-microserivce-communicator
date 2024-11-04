<?php

namespace MadMountainIo\MicroserviceCommunicator\Contracts;


use Illuminate\Redis\Connections\Connection;
use MadMountainIo\MicroserviceCommunicator\Brokers\MessageBrokerInterface;

class RedisStreamBroker implements MessageBrokerInterface
{
    private string $groupName;
    private mixed $sleeper;

    public function __construct(
        private readonly Connection $redis,
        array $config,
        callable $sleeper = null
    ) {
        $this->groupName = $config['group_name'] ?? 'default_group';

        $this->sleeper = $sleeper ?? 'usleep';

    }

    public function publish(string $topic, array $message): bool
    {
        try {
            $this->redis->xAdd(
                $topic,
                '*',
                ['payload' => json_encode($message)]
            );
            return true;
        } catch (\Exception $e) {
            \Log::error("Redis Stream publish error: ".$e->getMessage());
            return false;
        }
    }

    public function subscribe(string $topic, callable $callback): void
    {
        try {
            $this->redis->xGroup('CREATE', $topic, $this->groupName, '0', 'MKSTREAM');
        } catch (\Exception $e) {
            // Group may already exist
        }

        while (true) {
            $messages = $this->redis->xReadGroup(
                $this->groupName,
                'consumer_'.uniqid(),
                [$topic => '>'],
                1,
                0
            );

            if ($messages) {
                foreach ($messages[$topic] as $id => $message) {
                    $callback(json_decode($message['payload'], true));
                    $this->redis->xAck($topic, $this->groupName, [$id]);
                }
            }

            ($this->sleeper)(100000);
        }
    }
}