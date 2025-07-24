<?php

declare(strict_types=1);

return [
    'name'        => 'Amazon SES Bundle',
    'description' => 'Send emails through Amazon Simple Email Service (SES) with webhook support for bounces and complaints',
    'version'     => '1.0.0',
    'author'      => 'Rhafaman',
    
    'services' => [
        'other' => [
            // Transport factory será registrado automaticamente via services.php
            // EventSubscriber será registrado automaticamente via autowiring
        ],
    ],
]; 