<?php

declare(strict_types=1);

return [
    'name'        => 'Amazon SES Bundle',
    'description' => 'Send emails through Amazon Simple Email Service (SES) with webhook support for bounces and complaints',
    'version'     => '1.1.4',
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
]; 