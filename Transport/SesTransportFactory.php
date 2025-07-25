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
                $sesFactory = new \Symfony\Component\Mailer\Bridge\Amazon\Transport\SesTransportFactory(
                    $this->dispatcher,
                    $this->client,
                    $this->logger
                );
                return $sesFactory->create($dsn);
            } else {
                throw new \RuntimeException('Symfony Amazon SES Bridge is not installed. Run: composer require symfony/amazon-mailer');
            }
        }

        // Handle ses+smtp scheme by converting to standard SMTP DSN
        if ($scheme === 'ses+smtp') {
            $region = $dsn->getOption('region', 'us-east-1');
            $smtpHost = "email-smtp.{$region}.amazonaws.com";
            $port = $dsn->getPort() ?: 587;
            
            // Create SMTP DSN for Amazon SES
            // Amazon SES uses: 587 with STARTTLS, 465 with SSL
            $smtpDsn = new Dsn(
                'smtp',
                $smtpHost,
                $dsn->getUser(),
                $dsn->getPassword(),
                $port,
                [
                    'encryption' => $port === 465 ? 'ssl' : 'starttls',
                    'auth_mode' => 'login'
                ]
            );
            
            // Use the SMTP factory to create the actual transport
            $smtpFactory = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory(
                $this->dispatcher,
                $this->client,
                $this->logger
            );
            
            return $smtpFactory->create($smtpDsn);
        }

        throw new \InvalidArgumentException(sprintf('The "%s" scheme is not supported.', $scheme));
    }

    protected function getSupportedSchemes(): array
    {
        return ['ses+api', 'ses+smtp'];
    }
} 