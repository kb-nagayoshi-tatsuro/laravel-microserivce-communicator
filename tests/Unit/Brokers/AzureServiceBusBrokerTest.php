<?php


use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use MadMountainIo\MicroserviceCommunicator\Contracts\AzureServiceBusBroker;
use MadMountainIo\MicroserviceCommunicator\Exceptions\BrokerException;

beforeEach(function () {
    $this->mockHandler = new MockHandler();
    $handlerStack = HandlerStack::create($this->mockHandler);
    $client = new Client(['handler' => $handlerStack]);

    $this->broker = new AzureServiceBusBroker([
        'endpoint' => 'https://test.servicebus.windows.net',
        'shared_access_key_name' => 'test',
        'shared_access_key' => 'test-key'
    ]);

    // Replace the Guzzle client with our mocked version
    $reflection = new \ReflectionClass($this->broker);
    $property = $reflection->getProperty('client');
    $property->setAccessible(true);
    $property->setValue($this->broker, $client);
});

it('successfully publishes message', function () {
    $this->mockHandler->append(new Response(201));

    $result = $this->broker->publish('test-topic', ['test' => 'data']);

    expect($result)->toBeTrue();
});

it('handles publish failure', function () {
    $this->mockHandler->append(new Response(500));

    $this->broker->publish('test-topic', ['test' => 'data']);
})->throws(BrokerException::class);