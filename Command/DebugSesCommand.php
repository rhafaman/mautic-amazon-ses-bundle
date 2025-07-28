<?php

declare(strict_types=1);

namespace MauticPlugin\AmazonSESBundle\Command;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mime\Email;
use MauticPlugin\AmazonSESBundle\EventSubscriber\CallbackSubscriber;
use MauticPlugin\AmazonSESBundle\AmazonSESBundle;
use Aws\Ses\SesClient;
use GuzzleHttp\Client;

/**
 * Command to debug and test Amazon SES configuration
 * Helps diagnose InvalidSignatureException and other SES issues
 */
class DebugSesCommand extends Command
{
    protected static $defaultName = 'mautic:amazon-ses:debug';
    protected static $defaultDescription = 'Debug Amazon SES configuration and test email sending';

    // Suported SES schemes
    private const SUPPORTED_SES_SCHEMES = ['ses+api', 'ses+smtp'];

    public function __construct(
        private CoreParametersHelper $coreParametersHelper,
        private LoggerInterface $logger,
        private MailerInterface $mailer
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription(self::$defaultDescription)
            ->addOption(
                'test-email',
                't',
                InputOption::VALUE_OPTIONAL,
                'Send a test email to the specified address'
            )
            ->addOption(
                'from',
                'f',
                InputOption::VALUE_OPTIONAL,
                'From email address for test email (auto-detected if not provided)'
            )
            ->addOption(
                'test-connection',
                'c',
                InputOption::VALUE_NONE,
                'Test real connection to AWS SES'
            )
            ->addOption(
                'test-smtp-connectivity',
                null,
                InputOption::VALUE_NONE,
                'Test SMTP connectivity to Amazon SES endpoints (ports 587 and 465)'
            )
            ->addOption(
                'test-schemes',
                's',
                InputOption::VALUE_NONE,
                'Test if Mautic recognizes ses+api and ses+smtp schemes'
            )
            ->setHelp(
                'This command helps debug Amazon SES configuration issues, especially InvalidSignatureException errors. ' .
                'Supports both ses+api and ses+smtp schemes.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('🔍 Amazon SES Debug Tool');
        $io->text('<comment>Versão Melhorada - Suporte ses+api e ses+smtp</comment>');
        
        try {
            // 1. Analyze DSN Configuration
            $this->analyzeDsnConfiguration($io);
            
            // 2. Check System Environment
            $this->checkSystemEnvironment($io);
            
            // 3. Test SMTP connectivity if requested
            if ($input->getOption('test-smtp-connectivity')) {
                $this->testSmtpConnectivity($io);
            }
            
            // 4. Test scheme recognition if requested
            if ($input->getOption('test-schemes')) {
                $this->testSchemesRecognition($io);
            }
            
            // 5. Test AWS connection if requested
            if ($input->getOption('test-connection')) {
                $this->testAwsConnection($io);
            }
            
            // 6. Test email sending if requested
            $testEmail = $input->getOption('test-email');
            if ($testEmail) {
                $fromEmail = $input->getOption('from');
                
                // Se não foi fornecido um email 'from', tenta obter um dinamicamente
                if (!$fromEmail) {
                    $fromEmail = $this->getDefaultFromEmail();
                    if (!$fromEmail) {
                        $io->error('❌ Não foi possível determinar um email remetente padrão. Use --from=email@domain.com');
                        return Command::FAILURE;
                    }
                    $io->text("📧 Email remetente detectado automaticamente: <info>$fromEmail</info>");
                }
                
                $this->testEmailSending($io, $testEmail, $fromEmail);
            }
            
            $io->success('Debug analysis completed. Check the output above for any issues.');
            $io->note('Use --test-connection to test AWS connectivity and --test-email=email@domain.com to send test email');
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error('Error during debug analysis: ' . $e->getMessage());
            $this->logger->error('SES Debug Command Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    private function analyzeDsnConfiguration(SymfonyStyle $io): void
    {
        $io->section('📋 DSN Configuration Analysis');
        
        $dsnString = $this->coreParametersHelper->get('mailer_dsn');
        $io->text("DSN String: <info>$dsnString</info>");
        
        try {
            $dsn = Dsn::fromString($dsnString);
            
            // Check scheme - NOW SUPPORTS BOTH ses+api AND ses+smtp
            $scheme = $dsn->getScheme();
            if (in_array($scheme, self::SUPPORTED_SES_SCHEMES)) {
                $io->text("✅ Scheme: <info>$scheme</info> (supported)");
                
                if ($scheme === 'ses+smtp') {
                    $io->text("ℹ️  Using SMTP transport - make sure port 587 or 465 is accessible");
                } else {
                    $io->text("ℹ️  Using API transport - recommended for better performance");
                }
            } else {
                $io->text("❌ Scheme: <error>$scheme</error> (expected one of: " . implode(', ', self::SUPPORTED_SES_SCHEMES) . ")");
                return;
            }
            
            // Check host
            $host = $dsn->getHost();
            $io->text("🖥️  Host: <info>$host</info>");
            
            // Check port
            $port = $dsn->getPort();
            $io->text("🔌 Port: <info>$port</info>");
            
            if ($scheme === 'ses+smtp') {
                if (!in_array($port, [587, 465, 25])) {
                    $io->warning("For ses+smtp, recommended ports are 587 (STARTTLS) or 465 (SSL)");
                }
            }
            
            // Check user (Access Key)
            $user = $dsn->getUser();
            if ($user) {
                $userLength = strlen($user);
                $io->text("👤 Access Key: <info>" . substr($user, 0, 8) . "..." . substr($user, -4) . "</info> (length: $userLength)");
                
                // More flexible check - AWS access keys are typically 20 chars but can vary
                if ($userLength < 16 || $userLength > 128) {
                    $io->warning("Access Key length ($userLength) seems unusual. AWS Access Keys are typically 20 characters.");
                }
            } else {
                $io->text("❌ Access Key: <error>Missing</error>");
            }
            
            // Check password (Secret Key) - IMPROVED: More flexible validation
            $password = $dsn->getPassword();
            if ($password) {
                $passwordLength = strlen($password);
                $io->text("🔑 Secret Key: <info>***" . substr($password, -4) . "</info> (length: $passwordLength)");
                
                // More flexible validation - don't enforce strict 40 char limit
                if ($passwordLength < 20) {
                    $io->warning("Secret Key seems too short (length: $passwordLength)");
                } elseif ($passwordLength > 100) {
                    $io->warning("Secret Key seems too long (length: $passwordLength)");
                } else {
                    $io->text("✅ Secret Key length ($passwordLength) is acceptable");
                }
                
                // Check for special characters that might cause encoding issues
                $specialChars = ['+', '/', '=', '%', '&', '?', '#'];
                $foundSpecialChars = [];
                foreach ($specialChars as $char) {
                    if (strpos($password, $char) !== false) {
                        $foundSpecialChars[] = $char;
                    }
                }
                
                if (!empty($foundSpecialChars)) {
                    $io->warning("Secret Key contains special characters: " . implode(', ', $foundSpecialChars));
                    
                    // Only suggest URL encoding if not using ses+smtp
                    if ($scheme === 'ses+api') {
                        $io->text("💡 Consider URL-encoding the secret key if you get InvalidSignatureException");
                        
                        $encodedPassword = urlencode($password);
                        $suggestedDsn = sprintf(
                            'ses+api://%s:%s@%s?region=%s',
                            $user,
                            $encodedPassword,
                            $host,
                            $dsn->getOption('region', 'us-east-1')
                        );
                        $io->text("💡 Suggested DSN with URL encoding:");
                        $io->text("<comment>$suggestedDsn</comment>");
                    }
                }
                
                // Check if password is already URL encoded
                if ($password !== urldecode($password)) {
                    $io->text("ℹ️  Secret Key appears to be URL-encoded");
                }
                
            } else {
                $io->text("❌ Secret Key: <error>Missing</error>");
            }
            
            // Check region
            $region = $dsn->getOption('region');
            if ($region) {
                $io->text("🌍 Region: <info>$region</info>");
                
                if (!preg_match('/^[a-z]{2}-[a-z]+-\d+$/', $region)) {
                    $io->warning("Region format may be invalid. Expected format: us-east-1, eu-west-1, etc.");
                }
            } else {
                $io->text("❌ Region: <error>Missing</error>");
                $io->warning("Region is required. Add ?region=us-east-1 to your DSN");
            }
            
            // Show region option (Dsn class doesn't have getOptions() method)
            if ($region) {
                $io->text("⚙️  Options: <info>region=" . $region . "</info>");
            }
            
            // Symfony Mailer scheme compatibility check
            $this->checkSymfonyMailerCompatibility($io, $scheme);
            
            // Mostrar email padrão detectado
            $this->showDefaultFromEmail($io);
            
        } catch (\Exception $e) {
            $io->error("Failed to parse DSN: " . $e->getMessage());
        }
    }

    private function checkSymfonyMailerCompatibility(SymfonyStyle $io, string $scheme): void
    {
        $io->newLine();
        $io->text("<options=bold>🔧 Symfony Mailer Compatibility:</>");
        
        try {
            // Check if symfony/amazon-ses-mailer is available
            if (class_exists('Symfony\Component\Mailer\Bridge\Amazon\Transport\SesTransportFactory')) {
                $io->text("✅ Symfony Amazon SES Bridge: <info>Available</info>");
                
                if ($scheme === 'ses+api') {
                    $io->text("✅ ses+api scheme: <info>Fully supported by Symfony Amazon SES Bridge</info>");
                } elseif ($scheme === 'ses+smtp') {
                    $io->text("✅ ses+smtp scheme: <info>Supported via SMTP transport</info>");
                }
            } else {
                $io->text("⚠️  Symfony Amazon SES Bridge: <comment>Not detected</comment>");
                $io->text("   Make sure symfony/amazon-ses-mailer is installed for ses+api support");
            }
            
            // Check Symfony version
            if (defined('Symfony\Component\HttpKernel\Kernel::VERSION')) {
                $symfonyVersion = \Symfony\Component\HttpKernel\Kernel::VERSION;
                $io->text("📦 Symfony Version: <info>$symfonyVersion</info>");
            }
            
        } catch (\Exception $e) {
            $io->text("❌ Error checking Symfony compatibility: " . $e->getMessage());
        }
    }

    private function testAwsConnection(SymfonyStyle $io): void
    {
        $io->section('🔗 Testing AWS SES Connection');
        
        try {
            $dsnString = $this->coreParametersHelper->get('mailer_dsn');
            $dsn = Dsn::fromString($dsnString);
            
            if (!in_array($dsn->getScheme(), self::SUPPORTED_SES_SCHEMES)) {
                $io->error("Cannot test AWS connection with scheme: " . $dsn->getScheme());
                return;
            }
            
            // Extract credentials
            $accessKey = $dsn->getUser();
            $secretKey = $dsn->getPassword();
            $region = $dsn->getOption('region', 'us-east-1');
            
            if (!$accessKey || !$secretKey) {
                $io->error("Missing AWS credentials in DSN");
                return;
            }
            
            $io->text("Testing connection to AWS SES...");
            $io->text("Region: <info>$region</info>");
            
            // Create SES client for testing
            $sesClient = new SesClient([
                'version' => 'latest',
                'region' => $region,
                'credentials' => [
                    'key' => $accessKey,
                    'secret' => urldecode($secretKey), // Decode in case it's URL encoded
                ]
            ]);
            
            // Test 1: Get sending quota
            $io->text("📊 Testing: Get sending quota...");
            $quotaResult = $sesClient->getSendQuota();
            $quota = $quotaResult->toArray();
            
            $io->text("✅ Connection successful!");
            $io->text("📈 Daily sending quota: <info>" . number_format($quota['Max24HourSend']) . "</info>");
            $io->text("📊 Emails sent in last 24h: <info>" . number_format($quota['SentLast24Hours']) . "</info>");
            $io->text("⚡ Max send rate: <info>" . $quota['MaxSendRate'] . " emails/second</info>");
            
            // Test 2: List verified email addresses
            $io->text("📧 Testing: List verified identities...");
            $identitiesResult = $sesClient->listVerifiedEmailAddresses();
            $verifiedEmails = $identitiesResult->get('VerifiedEmailAddresses') ?: [];
            
            if (!empty($verifiedEmails)) {
                $io->text("✅ Verified email addresses found: <info>" . count($verifiedEmails) . "</info>");
                foreach (array_slice($verifiedEmails, 0, 5) as $email) {
                    $io->text("   📧 $email");
                }
                if (count($verifiedEmails) > 5) {
                    $io->text("   ... and " . (count($verifiedEmails) - 5) . " more");
                }
            } else {
                $io->warning("No verified email addresses found. You need to verify at least one email address or domain.");
            }
            
            // Test 3: Check account sending status
            $io->text("🔍 Testing: Account sending status...");
            $accountResult = $sesClient->getAccountSendingEnabled();
            $sendingEnabled = $accountResult->get('Enabled');
            
            if ($sendingEnabled) {
                $io->text("✅ Account sending: <info>Enabled</info>");
            } else {
                $io->error("❌ Account sending: Disabled");
            }
            
        } catch (\Exception $e) {
            $io->error("❌ AWS Connection failed: " . $e->getMessage());
            
            // Provide specific help for common errors
            $errorMessage = $e->getMessage();
            if (strpos($errorMessage, 'InvalidClientTokenId') !== false) {
                $io->text("💡 <comment>This error suggests invalid Access Key ID</comment>");
            } elseif (strpos($errorMessage, 'SignatureDoesNotMatch') !== false) {
                $io->text("💡 <comment>This error suggests invalid Secret Key or encoding issues</comment>");
                $io->text("   Try URL-encoding your secret key in the DSN");
            } elseif (strpos($errorMessage, 'InvalidUserID.NotFound') !== false) {
                $io->text("💡 <comment>This error suggests the AWS user doesn't exist</comment>");
            }
            
            $this->logger->error('AWS SES Connection Test Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function checkSystemEnvironment(SymfonyStyle $io): void
    {
        $io->section('🖥️  System Environment Check');
        
        // Time check
        $io->text("🕐 Current Time (UTC): <info>" . gmdate('Y-m-d H:i:s') . "</info>");
        $io->text("🕐 Local Time: <info>" . date('Y-m-d H:i:s') . "</info>");
        $io->text("🕐 Timezone: <info>" . date_default_timezone_get() . "</info>");
        
        // PHP version
        $io->text("🐘 PHP Version: <info>" . PHP_VERSION . "</info>");
        
        // Extensions check
        $requiredExtensions = ['curl', 'openssl', 'json'];
        foreach ($requiredExtensions as $ext) {
            if (extension_loaded($ext)) {
                $io->text("✅ Extension <info>$ext</info>: loaded");
            } else {
                $io->text("❌ Extension <error>$ext</error>: missing");
            }
        }
        
        // AWS SDK check
        if (class_exists('Aws\Ses\SesClient')) {
            $io->text("✅ AWS SDK: <info>Available</info>");
        } else {
            $io->text("⚠️  AWS SDK: <comment>Not available (install aws/aws-sdk-php for connection testing)</comment>");
        }
        
        // Memory limit
        $memoryLimit = ini_get('memory_limit');
        $io->text("💾 Memory Limit: <info>$memoryLimit</info>");
        
        // Network connectivity test
        $io->text("🌐 Testing network connectivity...");
        $this->testNetworkConnectivity($io);
    }
    
    private function testNetworkConnectivity(SymfonyStyle $io): void
    {
        try {
            $client = new Client(['timeout' => 10]);
            
            // Test AWS SES endpoint connectivity
            $response = $client->get('https://email.us-east-1.amazonaws.com/', [
                'http_errors' => false
            ]);
            
            if ($response->getStatusCode() < 500) {
                $io->text("✅ AWS SES endpoint: <info>Reachable</info>");
            } else {
                $io->text("⚠️  AWS SES endpoint: <comment>May have issues (status: " . $response->getStatusCode() . ")</comment>");
            }
            
        } catch (\Exception $e) {
            $io->text("❌ Network connectivity: <error>Issues detected</error>");
            $io->text("   Error: " . $e->getMessage());
        }
    }

    private function testSmtpConnectivity(SymfonyStyle $io): void
    {
        $io->section('🔗 Testing SMTP/SSL Connectivity');
        
        $dsnString = $this->coreParametersHelper->get('mailer_dsn');
        try {
            $dsn = Dsn::fromString($dsnString);
            $scheme = $dsn->getScheme();
            $host = $dsn->getHost();
            $port = $dsn->getPort();
            
            $io->text("Testing SMTP/SSL connectivity to $host on port $port...");
            
            if ($scheme === 'ses+smtp') {
                $io->text("ℹ️  Testing for STARTTLS (port 587)...");
                $this->testSmtpPort($io, $host, 587);
                $io->text("ℹ️  Testing for SSL (port 465)...");
                $this->testSmtpPort($io, $host, 465);
            } else {
                $io->text("ℹ️  Testing for port $port...");
                $this->testSmtpPort($io, $host, $port);
            }
            
        } catch (\Exception $e) {
            $io->error("Failed to parse DSN for SMTP connectivity test: " . $e->getMessage());
        }
    }

    private function testSmtpPort(SymfonyStyle $io, string $host, int $port): void
    {
        $io->text("Attempting to connect to $host on port $port...");
        
        try {
            $client = new Client([
                'timeout' => 10,
                'verify' => false // Disable SSL verification for testing
            ]);
            
            $response = $client->request('HEAD', "https://$host:$port", [
                'http_errors' => false
            ]);
            
            if ($response->getStatusCode() < 500) {
                $io->success("✅ Connection successful to $host:$port (Status: " . $response->getStatusCode() . ")");
                $io->text("   (This might indicate a firewall issue or network problem)");
            } else {
                $io->warning("⚠️  Connection failed to $host:$port (Status: " . $response->getStatusCode() . ")");
                $io->text("   (This might indicate a firewall issue, network problem, or Amazon SES is down)");
            }
        } catch (\Exception $e) {
            $io->error("❌ Connection failed to $host:$port: " . $e->getMessage());
            $io->text("   (This might indicate a firewall issue, network problem, or Amazon SES is down)");
        }
    }

    private function testEmailSending(SymfonyStyle $io, string $toEmail, string $fromEmail): void
    {
        $io->section('📧 Testing Email Sending');
        
        try {
            $timestamp = date('Y-m-d H:i:s');
            $email = (new Email())
                ->from($fromEmail)
                ->to($toEmail)
                ->subject("Amazon SES Test Email - $timestamp")
                ->text("This is a test email sent via Amazon SES to verify the configuration.\n\nTimestamp: $timestamp\nFrom: Mautic SES Debug Tool")
                ->html("
                    <h2>Amazon SES Test Email</h2>
                    <p>This is a test email sent via <strong>Amazon SES</strong> to verify the configuration.</p>
                    <ul>
                        <li><strong>Timestamp:</strong> $timestamp</li>
                        <li><strong>From:</strong> Mautic SES Debug Tool</li>
                        <li><strong>Transport:</strong> Amazon SES</li>
                    </ul>
                    <p><em>If you received this email, your Amazon SES configuration is working correctly!</em></p>
                ");
            
            $io->text("Sending test email...");
            $io->text("From: <info>$fromEmail</info>");
            $io->text("To: <info>$toEmail</info>");
            $io->text("Subject: <info>Amazon SES Test Email - $timestamp</info>");
            
            $startTime = microtime(true);
            $this->mailer->send($email);
            $sendTime = round((microtime(true) - $startTime) * 1000, 2);
            
            $io->success("✅ Test email sent successfully!");
            $io->text("⚡ Send time: <info>{$sendTime}ms</info>");
            $io->note("Check the recipient's inbox (and spam folder) for the test email.");
            
        } catch (\Exception $e) {
            $io->error("❌ Failed to send test email: " . $e->getMessage());
            
            // Enhanced error analysis
            $errorMessage = $e->getMessage();
            
            if (strpos($errorMessage, 'InvalidSignatureException') !== false || 
                strpos($errorMessage, 'signature') !== false) {
                
                $io->warning("🚨 InvalidSignatureException detected - running specific debug:");
                CallbackSubscriber::debugSignatureException($this->logger, $this->coreParametersHelper);
                $io->text("Check your logs for detailed signature debugging information.");
                
            } elseif (strpos($errorMessage, 'MessageRejected') !== false) {
                $io->text("💡 <comment>Email was rejected. Possible causes:</comment>");
                $io->text("   - From address not verified in SES");
                $io->text("   - Account in sandbox mode (can only send to verified addresses)");
                $io->text("   - Daily sending quota exceeded");
                
            } elseif (strpos($errorMessage, 'Throttling') !== false) {
                $io->text("💡 <comment>Sending rate exceeded. Wait and try again.</comment>");
                
            } elseif (strpos($errorMessage, 'AccessDenied') !== false) {
                $io->text("💡 <comment>Access denied. Check IAM permissions for SES operations.</comment>");
            }
            
            $this->logger->error('SES Test Email Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function testSchemesRecognition(SymfonyStyle $io): void
    {
        $io->section('🔧 Testing Scheme Recognition');
        
        try {
            // Test current DSN
            $dsnString = $this->coreParametersHelper->get('mailer_dsn');
            $dsn = Dsn::fromString($dsnString);
            
            $scheme = $dsn->getScheme();
            $io->text("Current DSN Scheme: <info>$scheme</info>");
            
            if (in_array($scheme, self::SUPPORTED_SES_SCHEMES)) {
                $io->success("✅ Plugin recognizes the scheme: <info>$scheme</info>");
            } else {
                $io->error("❌ Plugin does NOT recognize the scheme: <e>$scheme</e>");
            }
            
            // Test Symfony Mailer Transport Factory Recognition
            $io->newLine();
            $io->text("<options=bold>🚀 Testing Symfony Mailer Transport Factory:</>");
            
            $testSchemes = ['ses+api', 'ses+smtp'];
            foreach ($testSchemes as $testScheme) {
                $this->testTransportFactoryForScheme($io, $testScheme);
            }
            
        } catch (\Exception $e) {
            $io->error("Failed to test scheme recognition: " . $e->getMessage());
        }
    }

    private function testTransportFactoryForScheme(SymfonyStyle $io, string $scheme): void
    {
        try {
            // Create a test DSN with the given scheme
            $testDsn = new Dsn(
                $scheme,
                'default',
                'AKIATEST',
                'TestSecretKey',
                465,
                ['region' => 'us-east-1']
            );
            
            $io->text("Testing scheme: <info>$scheme</info>");
            
            // Try to get the mailer service and test transport creation
            if ($this->mailer instanceof \Symfony\Component\Mailer\Mailer) {
                $io->text("   📦 Mailer service: <info>Available</info>");
                
                // Check if we can find a transport factory for this scheme
                $transportFactories = $this->getTransportFactories();
                $factoryFound = false;
                
                foreach ($transportFactories as $factory) {
                    if (method_exists($factory, 'supports') && $factory->supports($testDsn)) {
                        $factoryFound = true;
                        $io->text("   ✅ Transport Factory: <info>Found (" . get_class($factory) . ")</info>");
                        
                        // Try to create the transport
                        try {
                            $transport = $factory->create($testDsn);
                            $io->text("   ✅ Transport Creation: <info>Success (" . get_class($transport) . ")</info>");
                        } catch (\Exception $e) {
                            $io->text("   ⚠️  Transport Creation: <comment>Failed - " . $e->getMessage() . "</comment>");
                        }
                        break;
                    }
                }
                
                if (!$factoryFound) {
                    $io->text("   ❌ Transport Factory: <e>NOT FOUND</e>");
                    $io->text("      <comment>This is why you get 'bridge is not installed' error!</comment>");
                }
            } else {
                $io->text("   ❌ Mailer service: <e>Not available</e>");
            }
            
        } catch (\Exception $e) {
            $io->text("   ❌ Error testing $scheme: <e>" . $e->getMessage() . "</e>");
        }
        
        $io->newLine();
    }

    private function getTransportFactories(): array
    {
        $factories = [];
        
        // Add standard factories
        $factories[] = new \Symfony\Component\Mailer\Transport\NullTransportFactory();
        $factories[] = new \Symfony\Component\Mailer\Transport\SendmailTransportFactory();
        $factories[] = new \Symfony\Component\Mailer\Transport\NativeTransportFactory();
        $factories[] = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory();
        
        // Try to add Amazon SES factory if available
        if (class_exists('Symfony\Component\Mailer\Bridge\Amazon\Transport\SesTransportFactory')) {
            $factories[] = new \Symfony\Component\Mailer\Bridge\Amazon\Transport\SesTransportFactory();
        }
        
        // ✅ Add our custom SES Transport Factory
        if (class_exists('MauticPlugin\AmazonSESBundle\Transport\SesTransportFactory')) {
            $factories[] = new \MauticPlugin\AmazonSESBundle\Transport\SesTransportFactory();
        }
        
        return $factories;
    }

    /**
     * Obtém o email padrão dinamicamente baseado nas configurações do sistema
     */
    private function getDefaultFromEmail(): ?string
    {
        // 1. Tenta obter do parâmetro mailer_from_email (configuração padrão do Mautic)
        $fromEmail = $this->coreParametersHelper->get('mailer_from_email');
        if ($fromEmail) {
            return $fromEmail;
        }

        // 2. Tenta obter do parâmetro webmaster_email
        $webmasterEmail = $this->coreParametersHelper->get('webmaster_email');
        if ($webmasterEmail) {
            return $webmasterEmail;
        }

        // 3. Tenta extrair do DSN (caso seja um email completo no user)
        try {
            $dsnString = $this->coreParametersHelper->get('mailer_dsn');
            if ($dsnString) {
                $dsn = Dsn::fromString($dsnString);
                $user = $dsn->getUser();
                
                // Verifica se o user parece ser um email (contém @ e ponto)
                if ($user && filter_var($user, FILTER_VALIDATE_EMAIL)) {
                    return $user;
                }
            }
        } catch (\Exception $e) {
            // Ignora erros de parsing do DSN para este propósito
        }

        // 4. Fallback para emails comuns do sistema
        $commonEmails = [
            'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'),
            'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'),
            'admin@' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
        ];

        foreach ($commonEmails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        return null;
    }

    /**
     * Mostra o email padrão detectado para o usuário
     */
    private function showDefaultFromEmail(SymfonyStyle $io): void
    {
        $io->newLine();
        $io->text("<options=bold>📧 Email Remetente Padrão:</>");
        
        $defaultEmail = $this->getDefaultFromEmail();
        
        if ($defaultEmail) {
            $io->text("✅ Email detectado: <info>$defaultEmail</info>");
            
            // Identificar a fonte do email
            $source = $this->identifyEmailSource($defaultEmail);
            $io->text("📍 Fonte: <comment>$source</comment>");
            
            $io->text("💡 Use este email com: <comment>--from=$defaultEmail</comment>");
        } else {
            $io->text("⚠️  Nenhum email padrão detectado");
            $io->text("💡 Especifique um email com: <comment>--from=email@domain.com</comment>");
        }
    }

    /**
     * Identifica a fonte do email padrão
     */
    private function identifyEmailSource(string $email): string
    {
        // Verifica mailer_from_email
        if ($this->coreParametersHelper->get('mailer_from_email') === $email) {
            return 'Configuração mailer_from_email do Mautic';
        }
        
        // Verifica webmaster_email
        if ($this->coreParametersHelper->get('webmaster_email') === $email) {
            return 'Configuração webmaster_email do Mautic';
        }
        
        // Verifica se veio do DSN
        try {
            $dsnString = $this->coreParametersHelper->get('mailer_dsn');
            if ($dsnString) {
                $dsn = Dsn::fromString($dsnString);
                if ($dsn->getUser() === $email) {
                    return 'Usuário do DSN (mailer_dsn)';
                }
            }
        } catch (\Exception $e) {
            // Ignora erros
        }
        
        // Verifica se é um dos fallbacks baseados no host
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        if (strpos($email, "@$host") !== false) {
            return "Gerado automaticamente baseado no host ($host)";
        }
        
        return 'Fonte desconhecida';
    }
} 