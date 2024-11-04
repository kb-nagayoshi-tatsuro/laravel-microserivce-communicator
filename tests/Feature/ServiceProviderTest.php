<?php

use MadMountainIo\MicroserviceCommunicator\MicroserviceCommunicationManager;

it('registers manager in service container', function () {
    $manager = app(MicroserviceCommunicationManager::class);

    expect($manager)->toBeInstanceOf(MicroserviceCommunicationManager::class);
});