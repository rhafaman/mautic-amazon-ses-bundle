<?php

declare(strict_types=1);

namespace MauticPlugin\AmazonSESBundle;

use Mautic\IntegrationsBundle\Bundle\AbstractPluginBundle;

/**
 * Amazon SES Bundle for Mautic 6.0
 * Following official plugin pattern
 * Supports both ses+api and ses+smtp schemes
 */
class AmazonSESBundle extends AbstractPluginBundle
{
    public const AMAZON_SES_API_SCHEME = 'ses+api';
    public const AMAZON_SES_SMTP_SCHEME = 'ses+smtp';
    
    // Supported schemes - includes both API and SMTP
    public const SUPPORTED_SCHEMES = [
        self::AMAZON_SES_API_SCHEME,
        self::AMAZON_SES_SMTP_SCHEME
    ];
    
    /**
     * Check if the given scheme is supported by this plugin
     */
    public static function isSupportedScheme(string $scheme): bool
    {
        return in_array($scheme, self::SUPPORTED_SCHEMES);
    }
} 