<?php

namespace MadMountainIo\MicroserviceCommunicator\Tests\Unit;

use Illuminate\Redis\Connections\Connection;
use MadMountainIo\MicroserviceCommunicator\Contracts\RedisStreamBroker;
use Mockery;

beforeEach(function () {
    $this->redisMock = Mockery::mock(Connection::class);
});

it('processes messages in subscription loop', function () {
    // Arrange
    $topic = 'test-topic';
    $messageId = '1234567890123-0';
    $messageData = ['test' => 'data'];
    $loopCount = 0;

    // Create broker with mock sleep function
    $broker = new RedisStreamBroker(
        $this->redisMock,
        ['group_name' => 'test-group'],
        function($microseconds) use (&$loopCount) {
            expect($microseconds)->toBe(100000);
            $loopCount++;
            if ($loopCount >= 2) {
                throw new \Exception('Break loop');
            }
        }
    );

    // Mock group creation
    $this->redisMock
        ->shouldReceive('xGroup')
        ->once()
        ->with('CREATE', $topic, 'test-group', '0', 'MKSTREAM')
        ->andReturn(true);

    // Mock message reading
    $this->redisMock
        ->shouldReceive('xReadGroup')
        ->once()
        ->withArgs(function ($group, $consumer, $streams) use ($topic) {
            return $group === 'test-group'
                && str_starts_with($consumer, 'consumer_')
                && $streams[$topic] === '>';
        })
        ->andReturn([
            $topic => [
                $messageId => ['payload' => json_encode($messageData)]
            ]
        ]);

    // Mock acknowledgment
    $this->redisMock
        ->shouldReceive('xAck')
        ->once()
        ->with($topic, 'test-group', [$messageId]);

    // Second xReadGroup call returns no messages
    $this->redisMock
        ->shouldReceive('xReadGroup')
        ->once()
        ->andReturn(null);

    // Track received messages
    $receivedMessages = [];

    try {
        $broker->subscribe($topic, function ($message) use (&$receivedMessages) {
            $receivedMessages[] = $message;
        });
    } catch (\Exception $e) {
        expect($e->getMessage())->toBe('Break loop');
    }

    // Verify results
    expect($receivedMessages)
        ->toHaveCount(1)
        ->sequence(
            fn ($message) => $message->toBe($messageData)
        );

    expect($loopCount)->toBe(2);
});

it('handles empty messages in subscription loop', function () {
    // Arrange
    $topic = 'test-topic';
    $loopCount = 0;

    // Create broker with mock sleep function
    $broker = new RedisStreamBroker(
        $this->redisMock,
        ['group_name' => 'test-group'],
        function($microseconds) use (&$loopCount) {
            expect($microseconds)->toBe(100000);
            $loopCount++;
            if ($loopCount >= 3) {
                throw new \Exception('Break loop');
            }
        }
    );

    // Mock group creation
    $this->redisMock
        ->shouldReceive('xGroup')
        ->once()
        ->with('CREATE', $topic, 'test-group', '0', 'MKSTREAM')
        ->andReturn(true);

    // Mock xReadGroup to always return null
    $this->redisMock
        ->shouldReceive('xReadGroup')
        ->times(3)
        ->andReturn(null);

    $callbackCalled = false;

    try {
        $broker->subscribe($topic, function () use (&$callbackCalled) {
            $callbackCalled = true;
        });
    } catch (\Exception $e) {
        expect($e->getMessage())->toBe('Break loop');
    }

    // Verify results
    expect($callbackCalled)->toBeFalse()
        ->and($loopCount)->toBe(3);
});

it('handles group creation errors', function () {
    // Arrange
    $topic = 'test-topic';
    $loopCount = 0;

    // Create broker with mock sleep function
    $broker = new RedisStreamBroker(
        $this->redisMock,
        ['group_name' => 'test-group'],
        function($microseconds) use (&$loopCount) {
            $loopCount++;
            if ($loopCount >= 2) {
                throw new \Exception('Break loop');
            }
        }
    );

    // Mock group creation error
    $this->redisMock
        ->shouldReceive('xGroup')
        ->once()
        ->with('CREATE', $topic, 'test-group', '0', 'MKSTREAM')
        ->andThrow(new \Exception('BUSYGROUP Consumer Group name already exists'));

    // Mock xReadGroup
    $this->redisMock
        ->shouldReceive('xReadGroup')
        ->twice()
        ->andReturn(null);

    try {
        $broker->subscribe($topic, fn() => null);
    } catch (\Exception $e) {
        expect($e->getMessage())->toBe('Break loop');
    }

    expect($loopCount)->toBe(2);
});