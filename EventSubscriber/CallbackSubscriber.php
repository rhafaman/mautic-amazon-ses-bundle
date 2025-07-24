<?php

declare(strict_types=1);

namespace MauticPlugin\AmazonSESBundle\EventSubscriber;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Event\TransportWebhookEvent;
use Mautic\EmailBundle\Model\TransportCallback;
use Mautic\LeadBundle\Entity\DoNotContact;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Transport\Dsn;
use MauticPlugin\AmazonSESBundle\AmazonSESBundle;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

use MauticPlugin\AmazonSESBundle\Services\AmazonSES\BouncedEmail;
use MauticPlugin\AmazonSESBundle\Services\AmazonSES\ComplaintEmail;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Amazon SES Callback Event Subscriber
 * Handles SNS webhooks for bounces and complaints following official plugin pattern
 */
class CallbackSubscriber implements EventSubscriberInterface
{
    protected TransportWebhookEvent $webhookEvent;
    protected array $payload;
    protected array $allowdTypes = ['Type', 'eventType', 'notificationType'];

    public function __construct(
        private TransportCallback $transportCallback,
        private CoreParametersHelper $coreParametersHelper,
        private LoggerInterface $logger,
        private Client $httpClient,
        private TranslatorInterface $translator
    ) {
    }

    /**
     * Process callback of AWS to register bounced and compilances
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EmailEvents::ON_TRANSPORT_WEBHOOK => 'processCallbackRequest',
        ];
    }

    public function processCallbackRequest(TransportWebhookEvent $webhookEvent): void
    {
        $this->webhookEvent = $webhookEvent;

        // 🔍 DEBUG: Log DSN configuration details
        $this->debugDsnConfiguration();

        $this->parseRequest();
        if (!$this->validateCallbackRequest()) {
            return;
        }
        $type = $this->parseType();

        try {
            $this->processJsonPayload($type);
        } catch (\Exception $e) {
            $message = 'AmazonCallback: ' . $e->getMessage();
            $this->logger->error($message);
            $this->webhookEvent->setResponse(new Response($message, Response::HTTP_BAD_REQUEST));
            return;
        }

        $this->webhookEvent->setResponse(new Response("Callback processed: $type"));
    }

    /**
     * Parse request to correct content type
     */
    protected function parseRequest()
    {
        $request = $this->webhookEvent->getRequest();
        $contentType = $request->getContentType();
        switch ($contentType) {
            case 'json':
                $this->payload = $request->request->all();
                break;
            default:
                $this->payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
                break;
        }
    }

    /**
     * Validate if request callback is correct
     * @return bool
     */
    protected function validateCallbackRequest(): bool
    {
        try {
            // Valid if mailer transport is AWS SES
            $dsnString = $this->coreParametersHelper->get('mailer_dsn');
            $dsn = Dsn::fromString($dsnString);
            
            $this->logger->info("🔍 DEBUG: Validating callback request", [
                'dsn_scheme' => $dsn->getScheme(),
                'expected_scheme' => AmazonSESBundle::AMAZON_SES_API_SCHEME,
                'scheme_matches' => AmazonSESBundle::AMAZON_SES_API_SCHEME === $dsn->getScheme()
            ]);
            
            if (AmazonSESBundle::AMAZON_SES_API_SCHEME !== $dsn->getScheme()) {
                $this->logger->warning("🔍 DEBUG: DSN scheme mismatch - callback ignored", [
                    'current_scheme' => $dsn->getScheme(),
                    'expected_scheme' => AmazonSESBundle::AMAZON_SES_API_SCHEME
                ]);
                return false;
            }

            // Check data
            if (!is_array($this->payload)) {
                $message = 'There is no data to process.';
                $this->logger->error($message . $this->webhookEvent->getRequest()->getContent());
                $this->webhookEvent->setResponse(new Response($message, Response::HTTP_BAD_REQUEST));
                return false;
            }

            //Check type
            if (
                !$this->arrayKeysExists($this->allowdTypes, $this->payload)
            ) {
                $message = "Type of request is invalid";
                $this->logger->error("🔍 DEBUG: Invalid callback type", [
                    'payload_keys' => array_keys($this->payload),
                    'allowed_types' => $this->allowdTypes,
                    'payload_sample' => array_slice($this->payload, 0, 3, true) // First 3 items for debugging
                ]);
                $this->webhookEvent->setResponse(new Response($message, Response::HTTP_BAD_REQUEST));
                return false;
            }

            $this->logger->info("🔍 DEBUG: Callback validation successful");
            return true;
            
        } catch (\Exception $e) {
            $this->logger->error("🔍 DEBUG: Exception during callback validation", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Process json request from Amazon SES.
     *
     * Based on: https://github.com/mzagmajster/mautic-ses-plugin/blob/main/Mailer/Callback/AmazonCallback.php
     *
     * @see https://docs.aws.amazon.com/ses/latest/dg/event-publishing-retrieving-sns-examples.html#event-publishing-retrieving-sns-bounce
     * @param array<string, mixed> $payload from Amazon SES
     */
    public function processJsonPayload(string $type, $message = ''): void
    {
        switch ($type) {
            case 'SubscriptionConfirmation':
                $this->processSubscriptionConfirmation();
                break;
            case 'Notification':
                $this->processNotification();
                break;
            case 'Complaint':
                $this->processComplaint();
                break;
            case 'Bounce':
                $this->processBounce();
                break;
            default:
                $message = "Received SES webhook of type: $type but couldn't understand payload: ";
                $this->logger->error($message . json_encode($this->payload));
                throw new BadRequestHttpException($message);
        }
    }

    /**
     * Process bounce type
     */
    protected function processBounce()
    {
        $bouncedEmail = new BouncedEmail($this->payload);

        // Process only permanent bounce
        if (!$bouncedEmail->shouldRemoved()) {
            return;
        }

        $emailId = $bouncedEmail->getHeaders('X-EMAIL-ID');
        $bouncedRecipients = $bouncedEmail->getRecipientDetails();

        foreach ($bouncedRecipients as $bouncedRecipient) {
            $address = Address::create($bouncedRecipient->getEmailAddress());
            $this->transportCallback
                ->addFailureByAddress($address->getAddress(), (string) $bouncedRecipient, DoNotContact::BOUNCED, $emailId);
            $this->logger->debug((string) $bouncedRecipient . ' ' . $bouncedEmail->getBounceSubType());
        }
    }

    /**
     * Process complaint type
     */
    protected function processComplaint()
    {
        $complaintEmail = new ComplaintEmail($this->payload);
        $complainedRecipients = $complaintEmail->getReceipts();

        foreach ($complainedRecipients as $complainedRecipient) {
            // http://docs.aws.amazon.com/ses/latest/DeveloperGuide/notification-contents.html#complaint-object
            switch ($complaintEmail->getComplaintFeedbackType()) {
                case 'abuse':
                    $reason = $this->translator->trans('mautic.plugin.scmailerses.complaint.reason.abuse');
                    break;
                case 'auth-failure':
                    $reason = $this->translator->trans('mautic.plugin.scmailerses.complaint.reason.auth_failure');
                    break;
                case 'fraud':
                    $reason = $this->translator->trans('mautic.plugin.scmailerses.complaint.reason.fraud');
                    break;
                case 'not-spam':
                    $reason = $this->translator->trans('mautic.plugin.scmailerses.complaint.reason.not_spam');
                    break;
                case 'other':
                    $reason = $this->translator->trans('mautic.plugin.scmailerses.complaint.reason.other');
                    break;
                case 'virus':
                    $reason = $this->translator->trans('mautic.plugin.scmailerses.complaint.reason.virus');
                    break;
                default:
                    $reason = $this->translator->trans('mautic.plugin.scmailerses.complaint.reason.unknown');
                    break;
            }

            $emailId = $complaintEmail->getHeaders('X-EMAIL-ID');
            $address = Address::create($complainedRecipient);
            $this->transportCallback->addFailureByAddress($address->getAddress(), $reason, DoNotContact::UNSUBSCRIBED, $emailId);

            $this->logger->debug("Unsubscribe email '" . $address->getAddress() . "'");
        }
    }

    /**
     * Confirm AWS SNS to revice callback
     * @see https://docs.aws.amazon.com/sns/latest/dg/SendMessageToHttp.prepare.html
     */
    protected function processSubscriptionConfirmation()
    {
        if (
            !isset($this->payload['SubscribeURL'])
            || !filter_var($this->payload['SubscribeURL'], FILTER_VALIDATE_URL)
        ) {
            $message = 'Invalid SubscribeURL';
            $this->logger->error($message);
            throw new BadRequestHttpException($message);
        }
        // Confirm Amazon SNS subscription by calling back the SubscribeURL from the playload
        try {
            $response = $this->httpClient->get($this->payload['SubscribeURL']);
            if ($response->getStatusCode() == Response::HTTP_OK) {
                $this->logger->info('Callback to SubscribeURL from Amazon SNS successfully');
            }
        } catch (TransferException $e) {
            $message = 'Callback to SubscribeURL from Amazon SNS failed, reason: ' . $e->getMessage();
            $this->logger->error($message);
            throw new BadRequestHttpException($message);
        }
    }

    /**
     * Process notificacion callback
     */
    protected function processNotification()
    {
        $subject = isset($this->payload['Subject']) ?
            $this->payload['Subject'] :
            'Not subject';
        $message = isset($this->payload['Message']) ?
            $this->payload['Message'] :
            'Not message';
        $data = "$subject: $message";
        $this->logger->info($data);
    }

    /**
     * Get Type of callback
     */
    protected function parseType(): string
    {
        $type = array_intersect_key(
            array_flip($this->allowdTypes),
            array_flip(array_keys($this->payload))
        );
        $key = array_keys($type)[0];
        return $this->payload[$key];
    }

    /**
     * Utility function to identify if a array of keys exist
     */
    protected function arrayKeysExists(array $keys, array $array): bool
    {
        $diff = array_intersect_key(
            array_flip($keys),
            array_flip(array_keys($array))
        );
        return count($diff) > 0;
    }

    /**
     * 🔍 DEBUG: Log DSN configuration details to help diagnose InvalidSignatureException
     */
    protected function debugDsnConfiguration(): void
    {
        try {
            $dsnString = $this->coreParametersHelper->get('mailer_dsn');
            $this->logger->info("🔍 DEBUG DSN String: " . $dsnString);

            $dsn = Dsn::fromString($dsnString);
            
            // Log DSN components
            $this->logger->info("🔍 DEBUG DSN Components:", [
                'scheme' => $dsn->getScheme(),
                'host' => $dsn->getHost(),
                'port' => $dsn->getPort(),
                'user' => $dsn->getUser(),
                'password_length' => $dsn->getPassword() ? strlen($dsn->getPassword()) : 0,
                'password_has_special_chars' => $dsn->getPassword() ? $this->hasSpecialChars($dsn->getPassword()) : false,
                'options' => $dsn->getOptions(),
            ]);

            // Check for potential encoding issues
            if ($dsn->getPassword()) {
                $originalPassword = $dsn->getPassword();
                $urlDecodedPassword = urldecode($originalPassword);
                
                if ($originalPassword !== $urlDecodedPassword) {
                    $this->logger->warning("🔍 DEBUG: Password seems to be URL encoded", [
                        'original_length' => strlen($originalPassword),
                        'decoded_length' => strlen($urlDecodedPassword),
                        'contains_percent' => strpos($originalPassword, '%') !== false
                    ]);
                }

                // Check for common special characters that cause signature issues
                $specialChars = ['+', '/', '=', '%', '&', '?', '#'];
                $foundSpecialChars = [];
                foreach ($specialChars as $char) {
                    if (strpos($originalPassword, $char) !== false) {
                        $foundSpecialChars[] = $char;
                    }
                }
                
                if (!empty($foundSpecialChars)) {
                    $this->logger->warning("🔍 DEBUG: Password contains special characters that may need encoding", [
                        'special_chars_found' => $foundSpecialChars
                    ]);
                }
            }

            // Validate region format
            $region = $dsn->getOption('region');
            if ($region) {
                $this->logger->info("🔍 DEBUG Region: " . $region);
                
                // Check if region format is valid (should be like us-east-1)
                if (!preg_match('/^[a-z]{2}-[a-z]+-\d+$/', $region)) {
                    $this->logger->warning("🔍 DEBUG: Region format may be invalid", [
                        'region' => $region,
                        'expected_format' => 'us-east-1, eu-west-1, etc.'
                    ]);
                }
            } else {
                $this->logger->warning("🔍 DEBUG: No region specified in DSN");
            }

        } catch (\Exception $e) {
            $this->logger->error("🔍 DEBUG: Error analyzing DSN configuration", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Check if string contains special characters
     */
    private function hasSpecialChars(string $str): bool
    {
        return preg_match('/[^a-zA-Z0-9]/', $str) === 1;
    }

    /**
     * 🔧 STATIC METHOD: Debug helper for InvalidSignatureException in email sending
     * Call this method from your Mautic email sending code when you get InvalidSignatureException
     * 
     * Usage: 
     * use MauticPlugin\AmazonSESBundle\EventSubscriber\CallbackSubscriber;
     * CallbackSubscriber::debugSignatureException($logger, $coreParametersHelper);
     */
    public static function debugSignatureException($logger, $coreParametersHelper): void
    {
        try {
            $dsnString = $coreParametersHelper->get('mailer_dsn');
            $logger->error("🚨 SIGNATURE DEBUG: InvalidSignatureException detected");
            $logger->error("🔍 DSN Analysis for InvalidSignatureException:", [
                'dsn_string' => $dsnString
            ]);

            $dsn = Dsn::fromString($dsnString);
            
            // Detailed analysis
            $analysis = [
                'scheme' => $dsn->getScheme(),
                'host' => $dsn->getHost(),
                'port' => $dsn->getPort(),
                'user_length' => $dsn->getUser() ? strlen($dsn->getUser()) : 0,
                'password_length' => $dsn->getPassword() ? strlen($dsn->getPassword()) : 0,
                'region' => $dsn->getOption('region'),
                'all_options' => $dsn->getOptions()
            ];

            $logger->error("🔍 DSN Components Analysis:", $analysis);

            // Check for encoding issues in credentials
            if ($dsn->getPassword()) {
                $password = $dsn->getPassword();
                $encodingCheck = [
                    'contains_plus' => strpos($password, '+') !== false,
                    'contains_slash' => strpos($password, '/') !== false,
                    'contains_equals' => strpos($password, '=') !== false,
                    'contains_percent' => strpos($password, '%') !== false,
                    'url_decoded_different' => $password !== urldecode($password),
                    'base64_like' => preg_match('/^[A-Za-z0-9+\/]*={0,2}$/', $password)
                ];
                
                $logger->error("🔍 Password Encoding Analysis:", $encodingCheck);

                // Suggest fixes
                if ($encodingCheck['contains_plus'] || $encodingCheck['contains_slash'] || $encodingCheck['contains_equals']) {
                    $encodedPassword = urlencode($password);
                    $suggestedDsn = sprintf(
                        'ses+api://%s:%s@default?region=%s',
                        $dsn->getUser(),
                        $encodedPassword,
                        $dsn->getOption('region', 'us-east-1')
                    );
                    
                    $logger->error("💡 SUGGESTED FIX: Try URL-encoded DSN:", [
                        'suggested_dsn' => $suggestedDsn,
                        'reason' => 'Special characters in password may need URL encoding'
                    ]);
                }
            }

            // Check region validity
            $region = $dsn->getOption('region');
            if (!$region) {
                $logger->error("💡 POSSIBLE FIX: No region specified. Add ?region=us-east-1 to DSN");
            } elseif (!preg_match('/^[a-z]{2}-[a-z]+-\d+$/', $region)) {
                $logger->error("💡 POSSIBLE FIX: Invalid region format", [
                    'current_region' => $region,
                    'valid_format_example' => 'us-east-1, eu-west-1, ap-southeast-1'
                ]);
            }

            // System time check
            $logger->error("🕐 System Time Check:", [
                'current_time_utc' => gmdate('Y-m-d H:i:s') . ' UTC',
                'current_timestamp' => time(),
                'timezone' => date_default_timezone_get()
            ]);

        } catch (\Exception $e) {
            $logger->error("🚨 Error during signature debugging:", [
                'debug_error' => $e->getMessage()
            ]);
        }
    }
} 