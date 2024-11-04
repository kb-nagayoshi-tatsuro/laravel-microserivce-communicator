# Laravel Microservice Communicator

A Laravel package for handling microservice communication via Azure Service Bus REST API and Redis Streams.

## Features

- 🚀 Azure Service Bus integration using REST API
- 📡 Redis Streams support with auto-recovery
- ♻️ Easy switching between messaging systems
- 🔄 Automatic consumer group management
- 🛡️ Error handling and logging
- ⚡ Asynchronous message processing
- 🔌 Simple integration with Laravel
- ✨ Full Laravel 11 support

## Requirements

- PHP 8.2 or higher
- Laravel 11.x
- Redis extension (for Redis driver)

## Installation

```bash
composer require madmountainio/laravel-microservice-communicator
```

For Laravel 11, ensure you're using the correct version:

```bash
composer require madmountainio/laravel-microservice-communicator "^2.0"
```

The package will automatically register its service provider in Laravel 11's new service provider discovery system.

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --provider="MadMountainIo\MicroserviceCommunicator\MicroserviceCommunicationServiceProvider"
```

Configure your environment variables in `.env`:

```env
# Choose your primary driver
MICROSERVICE_COMMUNICATION_DRIVER=azure

# Azure Service Bus Configuration
AZURE_SERVICE_BUS_ENDPOINT=https://your-namespace.servicebus.windows.net
AZURE_SERVICE_BUS_KEY_NAME=your-key-name
AZURE_SERVICE_BUS_KEY=your-key

# Redis Configuration (if using Redis driver)
REDIS_STREAM_CONNECTION=default
REDIS_STREAM_GROUP=your_app_group
```

## Usage with Laravel 11

### Basic Usage with Dependency Injection

```php
use MadMountainIo\MicroserviceCommunicator\MicroserviceCommunicationManager;

class YourService
{
    public function __construct(
        private readonly MicroserviceCommunicationManager $communicator
    ) {}

    public function sendMessage(): bool
    {
        return $this->communicator->publish('topic-name', [
            'event' => 'UserCreated',
            'data' => [
                'id' => 1,
                'email' => 'user@example.com'
            ]
        ]);
    }

    public function listenForMessages(): void
    {
        $this->communicator->subscribe('topic-name', function(array $message): void {
            logger()->info('Received message', $message);
            // Process your message
        });
    }
}
```

### Using with Laravel 11 Jobs

```php
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use MadMountainIo\MicroserviceCommunicator\MicroserviceCommunicationManager;

class ProcessMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly array $message
    ) {}

    public function handle(MicroserviceCommunicationManager $communicator): void
    {
        $communicator->publish('processed-messages', [
            'original_message' => $this->message,
            'processed_at' => now()->toIso8601String()
        ]);
    }
}
```

### Using with Laravel 11 Events

```php
use Illuminate\Foundation\Events\Dispatchable;
use MadMountainIo\MicroserviceCommunicator\MicroserviceCommunicationManager;

class MessageReceived
{
    use Dispatchable;

    public function __construct(
        public readonly array $message
    ) {}
}

// In your service provider
public function boot(): void
{
    Event::listen(function (MessageReceived $event) {
        // Process the message
    });
}
```

### Command Line Consumer with Laravel 11

```php
use Illuminate\Console\Command;
use MadMountainIo\MicroserviceCommunicator\MicroserviceCommunicationManager;

class ConsumeMessages extends Command
{
    protected $signature = 'messages:consume {topic} {--driver=azure}';
    
    public function handle(MicroserviceCommunicationManager $communicator): void
    {
        $communicator->subscribe($this->argument('topic'), function(array $message): void {
            $this->info('Processing message: ' . json_encode($message));
            // Process message
        });
    }
}
```

## Testing with Laravel 11

The package includes Pest tests. To run them:

```bash
composer test
```

### Writing Tests

Example using Laravel 11's new testing features:

```php
use MadMountainIo\MicroserviceCommunicator\MicroserviceCommunicationManager;

test('message is published successfully', function () {
    $manager = Mockery::mock(MicroserviceCommunicationManager::class);
    
    $manager->shouldReceive('publish')
        ->once()
        ->with('topic-name', ['key' => 'value'])
        ->andReturn(true);
        
    $this->app->instance(MicroserviceCommunicationManager::class, $manager);
    
    // Test your code
    expect(/* your test */)
        ->toBeTrue();
});
```

## Error Handling in Laravel 11

```php
use MadMountainIo\MicroserviceCommunicator\Exceptions\BrokerException;
use Illuminate\Support\Facades\Log;

try {
    $communicator->publish('topic', $message);
} catch (BrokerException $e) {
    Log::error('Failed to publish message', [
        'error' => $e->getMessage(),
        'topic' => 'topic',
        'message' => $message,
        'trace' => $e->getTrace()
    ]);
    
    report($e); // Uses Laravel 11's error reporting
}
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request. Make sure to:

1. Follow Laravel 11 coding standards
2. Add tests for new features
3. Update documentation
4. Follow semantic versioning

## License

This package is open-sourced software licensed under the MIT license.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.