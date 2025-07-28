<?php

declare(strict_types=1);

return [
    'name'        => 'Amazon SES Bundle',
    'description' => 'Send emails through Amazon Simple Email Service (SES) with webhook support for bounces and complaints',
    'version'     => '1.1.2',
    'author'      => 'Rhafaman',
    
    // Adicionar rotas se necessário (opcional para transport)
    'routes' => [],
    
    // Configuração de categorias (opcional)
    'categories' => [],
    
    // Configuração de menu (opcional para transport)
    'menu' => [],
    
    // Parâmetros do plugin (importante!)
    'parameters' => [
        'amazon_ses_region' => 'us-east-1',
        'amazon_ses_version' => 'latest',
        'amazon_ses_api_enabled' => true,
        'amazon_ses_webhook_enabled' => true,
    ],
    
    // Configuração de serviços (essencial)
    'services' => [
        'events' => [
            'mautic.amazon_ses.subscriber.callback' => [
                'class' => \MauticPlugin\AmazonSESBundle\EventSubscriber\CallbackSubscriber::class,
                'tag' => 'kernel.event_subscriber',
            ],
        ],
        'forms' => [],
        'models' => [],
        'integrations' => [],
        'others' => [
            'mautic.amazon_ses.transport.factory' => [
                'class' => \MauticPlugin\AmazonSESBundle\Transport\SesTransportFactory::class,
                'tag' => 'mailer.transport_factory',
            ],
        ],
    ],
]; 