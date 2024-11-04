<?php

namespace MadMountainIo\MicroserviceCommunicator\Messages\Traits;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use MadMountainIo\MicroserviceCommunicator\Exceptions\BrokerException;

trait MessageActions
{
    protected Client $client;
    protected string $queueName;
    protected string $lockToken;
    protected string $messageId;
    protected array $properties;
    protected mixed $logger;

    /**
     * @throws Exception
     * @throws GuzzleException
     * @throws BrokerException
     */
    public function complete(): void
    {
        try {
            $response = $this->client->delete(
                $this->buildUrl("messages/{$this->messageId}/{$this->lockToken}"),
            );

            if ($response->getStatusCode() !== 200) {
                throw new BrokerException(
                    "Failed to complete message. Status code: {$response->getStatusCode()}"
                );
            }

            $this->logger->info("Message completed", [
                'queue' => $this->queueName,
                'messageId' => $this->messageId
            ]);
        } catch (Exception $e) {
            $this->logger->error("Failed to complete message", [
                'queue' => $this->queueName,
                'messageId' => $this->messageId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * @throws Exception
     * @throws GuzzleException
     * @throws BrokerException
     */
    public function abandon(?string $reason = null, array $properties = []): void
    {
        try {
            $response = $this->client->put(
                $this->buildUrl("messages/{$this->messageId}/{$this->lockToken}"),
                [
                    'json' => array_merge(
                        ['MessageId' => $this->messageId],
                        $properties,
                        $reason ? ['AbandonReason' => $reason] : []
                    )
                ]
            );


            if ($response->getStatusCode() !== 200) {
                throw new BrokerException(
                    "Failed to abandon message. Status code: {$response->getStatusCode()}"
                );
            }

            $this->logger->info("Message abandoned", [
                'queue' => $this->queueName,
                'messageId' => $this->messageId,
                'reason' => $reason
            ]);
        } catch (\Exception $e) {
            $this->logger->error("Failed to abandon message", [
                'queue' => $this->queueName,
                'messageId' => $this->messageId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }


    private function buildUrl(string $path): string
    {
        return sprintf("/%s/%s", trim($this->queueName, '/'), trim($path, '/'));
    }
}