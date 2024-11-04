<?php

namespace MadMountainIo\MicroserviceCommunicator\Messages;

use GuzzleHttp\Client;
use MadMountainIo\MicroserviceCommunicator\Messages\Traits\MessageActions;

class ServiceBusMessage
{
    use MessageActions;

    private mixed $body;

    public function __construct(
        Client $client,
        string $queueName,
        string $lockToken,
        string $messageId,
        mixed $body,
        array $properties,
        mixed $logger
    ) {
        $this->client = $client;
        $this->queueName = $queueName;
        $this->lockToken = $lockToken;
        $this->messageId = $messageId;
        $this->body = $body;
        $this->properties = $properties;
        $this->logger = $logger;
    }

    public function getBody(): mixed
    {
        return $this->body;
    }

    public function getMessageId(): string
    {
        return $this->messageId;
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

}