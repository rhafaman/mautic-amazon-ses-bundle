<?php

declare(strict_types=1);

return [
    'name'        => 'Amazon SES Bundle',
    'description' => 'Send emails through Amazon Simple Email Service (SES) with webhook support for bounces and complaints',
    'version'     => '1.1.1',
    'author'      => 'Rhafaman',
    
    // ✅ OPCIONAL: Configurações específicas do plugin
    'parameters' => [
        'amazon_ses_region' => 'us-east-1',
        'amazon_ses_version' => 'latest',
    ],
    
    // ✅ OPCIONAL: Rotas customizadas (você não precisa)
    'routes' => [],
    
    // ✅ OPCIONAL: Configuração de serviços (funciona pelo autowiring)
    'services' => [
        'events' => [],
        'forms' => [],
        'models' => [],
        'integrations' => [],
        'others' => [],
    ],
]; 