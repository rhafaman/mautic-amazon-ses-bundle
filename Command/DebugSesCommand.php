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

/**
 * Command to debug and test Amazon SES configuration
 * Helps diagnose InvalidSignatureException and other SES issues
 */
class DebugSesCommand extends Command
{
    protected static $defaultName = 'mautic:amazon-ses:debug';
    protected static $defaultDescription = 'Debug Amazon SES configuration and test email sending';

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
                'From email address for test email',
                'noreply@example.com'
            )
            ->setHelp(
                'This command helps debug Amazon SES configuration issues, especially InvalidSignatureException errors.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('🔍 Amazon SES Debug Tool');
        
        try {
            // 1. Analyze DSN Configuration
            $this->analyzeDsnConfiguration($io);
            
            // 2. Check System Environment
            $this->checkSystemEnvironment($io);
            
            // 3. Test email sending if requested
            $testEmail = $input->getOption('test-email');
            if ($testEmail) {
                $fromEmail = $input->getOption('from');
                $this->testEmailSending($io, $testEmail, $fromEmail);
            }
            
            $io->success('Debug analysis completed. Check the output above for any issues.');
            
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
            
            // Check scheme
            $scheme = $dsn->getScheme();
            if ($scheme === AmazonSESBundle::AMAZON_SES_API_SCHEME) {
                $io->text("✅ Scheme: <info>$scheme</info> (correct)");
            } else {
                $io->text("❌ Scheme: <error>$scheme</error> (expected: " . AmazonSESBundle::AMAZON_SES_API_SCHEME . ")");
            }
            
            // Check host
            $host = $dsn->getHost();
            $io->text("🖥️  Host: <info>$host</info>");
            
            // Check port
            $port = $dsn->getPort();
            $io->text("🔌 Port: <info>$port</info>");
            
            // Check user (Access Key)
            $user = $dsn->getUser();
            if ($user) {
                $io->text("👤 Access Key: <info>" . substr($user, 0, 8) . "..." . substr($user, -4) . "</info> (length: " . strlen($user) . ")");
                
                if (strlen($user) !== 20) {
                    $io->warning("Access Key should be 20 characters long. Current length: " . strlen($user));
                }
            } else {
                $io->text("❌ Access Key: <error>Missing</error>");
            }
            
            // Check password (Secret Key)
            $password = $dsn->getPassword();
            if ($password) {
                $io->text("🔑 Secret Key: <info>***" . substr($password, -4) . "</info> (length: " . strlen($password) . ")");
                
                if (strlen($password) !== 40) {
                    $io->warning("Secret Key should be 40 characters long. Current length: " . strlen($password));
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
            
            // Show all options
            $options = $dsn->getOptions();
            if (!empty($options)) {
                $io->text("⚙️  All Options: <info>" . json_encode($options) . "</info>");
            }
            
        } catch (\Exception $e) {
            $io->error("Failed to parse DSN: " . $e->getMessage());
        }
    }

    private function checkSystemEnvironment(SymfonyStyle $io): void
    {
        $io->section('🖥️  System Environment Check');
        
        // Time check
        $io->text("🕐 Current Time (UTC): <info>" . gmdate('Y-m-d H:i:s') . "</info>");
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
        
        // Memory limit
        $memoryLimit = ini_get('memory_limit');
        $io->text("💾 Memory Limit: <info>$memoryLimit</info>");
    }

    private function testEmailSending(SymfonyStyle $io, string $toEmail, string $fromEmail): void
    {
        $io->section('📧 Testing Email Sending');
        
        try {
            $email = (new Email())
                ->from($fromEmail)
                ->to($toEmail)
                ->subject('Amazon SES Test Email - ' . date('Y-m-d H:i:s'))
                ->text('This is a test email sent via Amazon SES to verify the configuration.')
                ->html('<p>This is a test email sent via <strong>Amazon SES</strong> to verify the configuration.</p>');
            
            $io->text("Sending test email...");
            $io->text("From: <info>$fromEmail</info>");
            $io->text("To: <info>$toEmail</info>");
            
            $this->mailer->send($email);
            
            $io->success("✅ Test email sent successfully!");
            
        } catch (\Exception $e) {
            $io->error("❌ Failed to send test email: " . $e->getMessage());
            
            // If it's an InvalidSignatureException, provide specific debugging
            if (strpos($e->getMessage(), 'InvalidSignatureException') !== false || 
                strpos($e->getMessage(), 'signature') !== false) {
                
                $io->warning("🚨 InvalidSignatureException detected - running specific debug:");
                CallbackSubscriber::debugSignatureException($this->logger, $this->coreParametersHelper);
                $io->text("Check your logs for detailed signature debugging information.");
            }
            
            $this->logger->error('SES Test Email Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
} 