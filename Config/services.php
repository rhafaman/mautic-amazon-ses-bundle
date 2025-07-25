<?php

declare(strict_types=1);

use Mautic\CoreBundle\DependencyInjection\MauticCoreExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use MauticPlugin\AmazonSESBundle\Command\DebugSesCommand;
use MauticPlugin\AmazonSESBundle\Transport\SesTransportFactory;

return function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->public();

    $excludes = [
        'Services',
    ];

    $services->load('MauticPlugin\\AmazonSESBundle\\', '../')
        ->exclude('../{' . implode(',', array_merge(MauticCoreExtension::DEFAULT_EXCLUDES, $excludes)) . '}');

    // Register debug command
    $services->set(DebugSesCommand::class)
        ->tag('console.command');

    // ✅ Register Amazon SES Transport Factory
    $services->set(SesTransportFactory::class)
        ->tag('mailer.transport_factory');
}; 