<?php

declare(strict_types=1);

namespace MauticPlugin\AmazonSESBundle;

use Mautic\IntegrationsBundle\Bundle\AbstractPluginBundle;

/**
 * Amazon SES Bundle for Mautic 6.0
 * Following official plugin pattern
 */
class AmazonSESBundle extends AbstractPluginBundle
{
    public const AMAZON_SES_API_SCHEME = 'ses+api';
} 