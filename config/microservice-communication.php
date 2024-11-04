<?php


return [
    'default' => env('MICROSERVICE_COMMUNICATION_DRIVER', 'azure'),

    'connections' => [
        'azure' => [
            'endpoint' => env('AZURE_SERVICE_BUS_ENDPOINT'),
            'shared_access_key_name' => env('AZURE_SERVICE_BUS_KEY_NAME'),
            'shared_access_key' => env('AZURE_SERVICE_BUS_KEY'),
        ],
        'redis' => [
            'connection' => env('REDIS_STREAM_CONNECTION', 'default'),
            'group_name' => env('REDIS_STREAM_GROUP', 'default_group'),
        ],
    ],
];