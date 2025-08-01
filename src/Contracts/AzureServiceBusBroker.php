<?php

namespace MadMountainIo\MicroserviceCommunicator\Contracts;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use MadMountainIo\MicroserviceCommunicator\Brokers\MessageBrokerInterface;
use MadMountainIo\MicroserviceCommunicator\Exceptions\BrokerException;
use MadMountainIo\MicroserviceCommunicator\Messages\ServiceBusMessage;

class AzureServiceBusBroker implements MessageBrokerInterface
{
    private Client $client;
    private string $baseUrl;
    private string $sasToken;
    private string $keyName;
    private string $key;
    private const TOKEN_EXPIRY_TIME = 3600; // 1 hour
    private const RETRY_DELAY = 5; // seconds
    private const EMPTY_QUEUE_DELAY = 1; // second

    private mixed $logger;
    private int $tokenExpiresAt;

    /**
     * @throws BrokerException
     */
    public function __construct(array $config, mixed $logger = null)
    {
        $this->validateConfig($config);

        $this->baseUrl = $config['endpoint'];
        $this->keyName = $config['shared_access_key_name'];
        $this->key = $config['shared_access_key'];

        if (!$logger) {
            $this->logger = Log::getLogger();
        } else {
            $this->logger = $logger;
        }

        $this->refreshSasToken();

        $this->client = new Client([
            'base_uri' => rtrim($config['endpoint'], '/'),
            'timeout' => 30,
        ]);
    }

    /**
     * @throws BrokerException
     */
    private function validateConfig(array $config): void
    {
        $requiredKeys = ['endpoint', 'shared_access_key_name', 'shared_access_key'];

        foreach ($requiredKeys as $key) {
            if (empty($config[$key])) {
                throw new BrokerException("Missing required configuration key: {$key}");
            }
        }
    }

    private function refreshSasToken(): void
    {
        $this->sasToken = $this->generateSasToken(
            $this->baseUrl,
            $this->keyName,
            $this->key
        );
    }

    private function generateSasToken(string $resourceUri, string $keyName, string $key): string
    {
        $expiry = time() + self::TOKEN_EXPIRY_TIME;
        $this->setTokenExpiresAt($expiry);

        $stringToSign = urlencode($resourceUri)."\n".$expiry;
        $signature = base64_encode(hash_hmac('sha256', $stringToSign, $key, true));

        return sprintf(
            'SharedAccessSignature sr=%s&sig=%s&se=%s&skn=%s',
            urlencode($resourceUri),
            urlencode($signature),
            $expiry,
            $keyName
        );
    }

    /**
     * @throws BrokerException
     */
    public function publish(string $queueName, array $message): bool
    {
        try {
            $response = $this->client->post(
                $this->buildUrl($queueName, 'messages'),
                [
                    'json' => $message,
                    'headers' => $this->getHeaders()
                ]
            );
            
            if ($response->getStatusCode() !== 201) {
                throw new BrokerException(
                    "Failed to publish message. Status code: {$response->getStatusCode()}"
                );
            }

            return true;
        } catch (GuzzleException $e) {
            throw new BrokerException("Failed to publish message: ".$e->getMessage());
        }
    }

    public function subscribe(string $queueName, callable $callback): void
    {
        $this->logger->info("Starting subscription", ['queue' => $queueName]);

        while (true) {
            try {
                $message = $this->receiveMessage($queueName);

                if ($message === null) {
                    sleep(self::EMPTY_QUEUE_DELAY);
                    continue;
                }

                try {
                    $serviceBusMessage = new ServiceBusMessage(
                        $this->client,
                        $this->getHeaders(),
                        $queueName,
                        $message['lockToken'],
                        $message['properties']['MessageId'],
                        $message['body'],
                        $message['properties'],
                        $this->logger
                    );

                    // Pass the ServiceBusMessage instance to the callback
                    $callback($serviceBusMessage);

                    // Azure Service Bus キューに完了通知を行う
                    try {
                        $serviceBusMessage->complete();
                        $this->logger->info("Message successfully completed and removed from queue.",[
                            'messageId' => $serviceBusMessage->getMessageId()
                        ]);
                    } catch (\Exception $completeException) {
                        $this->logger->error("Failed to explicitly complete message after callback: " . $completeException->getMessage(), [
                            'messageId' => $serviceBusMessage->getMessageId(),
                            'trace'     => $completeException->getTraceAsString()
                        ]);
                    }

                } catch (\Exception $e) {
                    $this->logger->error("Error processing message", [
                        'queue' => $queueName,
                        'messageId' => $message['properties']['MessageId'] ?? 'unknown',
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    // エラー発生時はメッセージを破棄（abandon）またはデッドレター（deadLetter）
                    if ($serviceBusMessage && method_exists($serviceBusMessage, 'abandon')) {
                        try {
                            $serviceBusMessage->abandon();
                            $this->logger->info("Message abandoned due to processing error.", [
                                'messageId' => $serviceBusMessage->getMessageId()
                            ]);
                        } catch (\Throwable $abandonException) {
                            $this->logger->error("Failed to abandon message after processing error: " . $abandonException->getMessage(), [
                                'messageId' => $serviceBusMessage->getMessageId(),
                                'trace'     => $abandonException->getTraceAsString()
                            ]);
                        }
                    } else {
                        $this->logger->warning("No abandon method available or unable to access. Message will be re-delivered after lock expires.", [
                            'messageId' => $serviceBusMessage->getMessageId()
                        ]);
                    }
                }

            } catch (\Exception $e) {
                $this->logger->error("Unexpected error in subscription", [
                    'queue' => $queueName,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                sleep(self::RETRY_DELAY);
            }
        }
    }


    /**
     * @throws BrokerException
     */
    private function receiveMessage(string $queueName): ?array
    {
        try {
            $response = $this->client->post(
                $this->buildUrl($queueName, 'messages/head'),
                ['headers' => $this->getHeaders()]
            );

            if ($response->getStatusCode() === 204) {
                return null;
            }

            if ($response->getStatusCode() !== 201) {
                throw new BrokerException(
                    "Failed to receive message. Status code: {$response->getStatusCode()}"
                );
            }

            $brokerProperties = $this->extractBrokerProperties($response);

            return [
                'body' => json_decode($response->getBody()->getContents(), true),
                'lockToken' => $brokerProperties['LockToken'] ?? null,
                'properties' => $brokerProperties
            ];

        } catch (GuzzleException $e) {
            throw new BrokerException("Failed to receive message: ".$e->getMessage());
        }
    }

    public function getTokenExpiresAt(): int
    {
        return $this->tokenExpiresAt;
    }

    private function buildUrl(string $queueName, string $path): string
    {
        return sprintf("/%s/%s", trim($queueName, '/'), trim($path, '/'));
    }

    private function getHeaders(): array
    {
        if (time() > $this->getTokenExpiresAt()) {
            $this->refreshSasToken();
        }

        return [
            'Authorization' => $this->sasToken,
            'Content-Type' => 'application/json',
        ];
    }

    private function extractBrokerProperties($response): array
    {
        $brokerPropertiesHeader = $response->getHeader('BrokerProperties')[0] ?? '{}';
        return json_decode($brokerPropertiesHeader, true) ?? [];
    }

    private function setTokenExpiresAt(int $expiry): void
    {
        $this->tokenExpiresAt = $expiry;
    }
}