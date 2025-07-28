<?php

declare(strict_types=1);

namespace MauticPlugin\AmazonSESBundle\Transport;

use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Amazon SES Transport Factory for Mautic 6
 * Handles both ses+api and ses+smtp schemes
 * Enhanced with improved SSL/TLS handling and timeout configuration
 */
class SesTransportFactory extends AbstractTransportFactory
{
    public function __construct(
        EventDispatcherInterface $dispatcher = null,
        HttpClientInterface $client = null,
        LoggerInterface $logger = null
    ) {
        parent::__construct($dispatcher, $client, $logger);
    }

    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        
        if (!in_array($scheme, $this->getSupportedSchemes())) {
            throw new \InvalidArgumentException(sprintf('The "%s" scheme is not supported.', $scheme));
        }

        // Handle ses+api scheme using Symfony's native Amazon SES transport
        if ($scheme === 'ses+api') {
            // Check if Symfony Amazon SES Bridge is available
            if (class_exists('Symfony\Component\Mailer\Bridge\Amazon\Transport\SesTransportFactory')) {
                // Instalar: composer require symfony/amazon-mailer
                $officialFactory = new \Symfony\Component\Mailer\Bridge\Amazon\Transport\SesTransportFactory(
                    $this->dispatcher,
                    $this->client,
                    $this->logger
                );
                
                // Converter DSN para formato oficial
                $officialDsn = new Dsn(
                    'ses+api',
                    'default',
                    $dsn->getUser(),
                    $dsn->getPassword(),
                    null,
                    ['region' => $dsn->getOption('region', 'us-east-1')]
                );
                
                return $officialFactory->create($officialDsn);
            } else {
                throw new \RuntimeException('Symfony Amazon SES Bridge is not installed. Run: composer require symfony/amazon-mailer');
            }
        }

        // Handle ses+smtp scheme by converting to standard SMTP DSN
        if ($scheme === 'ses+smtp') {
            $region = $dsn->getOption('region', 'us-east-1');
            $smtpHost = "email-smtp.{$region}.amazonaws.com";
            
            // Amazon SES recommends port 587 with STARTTLS for better compatibility
            // Port 465 can cause timeout issues with some configurations
            $port = $dsn->getPort();
            
            // If no port specified or port 465 specified, use 587 (more reliable)
            if (!$port || $port === 465) {
                $port = 587;
                $encryption = 'starttls';
            } elseif ($port === 587) {
                $encryption = 'starttls';
            } elseif ($port === 465) {
                $encryption = 'ssl';
            } else {
                // For other ports, default to STARTTLS
                $encryption = 'starttls';
            }
            
            // Enhanced SMTP options for better Amazon SES compatibility
            $smtpOptions = [
                'encryption' => $encryption,
                'auth_mode' => 'login',
                // Connection timeout settings to prevent hanging
                'timeout' => 30,
                'stream_context_options' => [
                    'ssl' => [
                        // Improve SSL/TLS compatibility
                        'verify_peer' => true,
                        'verify_peer_name' => true,
                        'allow_self_signed' => false,
                        // Amazon SES uses valid certificates
                        'cafile' => null, // Use system CA bundle
                        'ciphers' => 'ECDHE+AESGCM:ECDHE+CHACHA20:DHE+AESGCM:DHE+CHACHA20:!aNULL:!MD5:!DSS',
                        // Set SNI hostname for proper SSL handshake
                        'SNI_enabled' => true,
                        'peer_name' => $smtpHost,
                    ],
                    'socket' => [
                        // Socket timeout for connection
                        'timeout' => 30,
                    ],
                ],
            ];
            
            // Create SMTP DSN for Amazon SES with enhanced options
            $smtpDsn = new Dsn(
                'smtp',
                $smtpHost,
                $dsn->getUser(),
                $dsn->getPassword(),
                $port,
                $smtpOptions
            );
            
            // Use the SMTP factory to create the actual transport
            $smtpFactory = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory(
                $this->dispatcher,
                $this->client,
                $this->logger
            );
            
            $transport = $smtpFactory->create($smtpDsn);
            
            // Set additional transport options for Amazon SES
            if (method_exists($transport, 'setStreamOptions')) {
                $transport->setStreamOptions($smtpOptions['stream_context_options']);
            }
            
            return $transport;
        }

        throw new \InvalidArgumentException(sprintf('The "%s" scheme is not supported.', $scheme));
    }

    protected function getSupportedSchemes(): array
    {
        return ['ses+api', 'ses+smtp'];
    }
} 