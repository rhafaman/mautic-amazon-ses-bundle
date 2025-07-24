# Mautic 6 Amazon SES Plugin

<p style="text-align: center;">
<img src="Assets/img/icon.png" alt="Amazon SES" width="200"/>
</p>

This plugin enables Mautic 6 to use AWS SES as email transport and provides callbacks to process bounces and complaints.

**✨ Enhanced Features:**
- ✅ Support for both `ses+api` and `ses+smtp` transport schemes
- ✅ SNS callback processing for bounces and complaints
- ✅ Advanced debug command for troubleshooting configuration issues
- ✅ Flexible secret key validation (supports keys of various lengths, including 44-character keys)
- ✅ Real AWS connection testing
- ✅ Automatic signature exception debugging

## INSTALLATION

1. Copy the plugin to your Mautic plugins directory:
```bash
cp -r examples/custom-plugins/plugins/AmazonSESBundle /path/to/mautic/plugins/
```

2. Clear cache:
```bash
php bin/console cache:clear
```

3. Install the plugin:
```bash
php bin/console mautic:plugins:reload
```

## CONFIGURATION

### Option 1: Using SES API (Recommended)

Configure using the `ses+api` scheme for best performance:

**DSN Format:** `ses+api://ACCESS_KEY:SECRET_KEY@default?region=REGION`

1. Navigate to Configuration > Mail Send Settings
2. Update the following fields:

| Field    | Value                    |
| -------- | ------------------------ |
| Scheme   | `ses+api`               |
| Host     | `default`               |
| Port     | `465`                   |
| User     | `<aws-access-key>`      |
| Password | `<aws-secret-key>`      |
| Region   | `<aws-region>`          |

### Option 2: Using SES SMTP

Configure using the `ses+smtp` scheme for SMTP transport:

**DSN Format:** `ses+smtp://ACCESS_KEY:SECRET_KEY@email-smtp.REGION.amazonaws.com:587?region=REGION`

1. Navigate to Configuration > Mail Send Settings
2. Update the following fields:

| Field    | Value                                     |
| -------- | ----------------------------------------- |
| Scheme   | `ses+smtp`                               |
| Host     | `email-smtp.<region>.amazonaws.com`     |
| Port     | `587` (STARTTLS) or `465` (SSL)         |
| User     | `<aws-access-key>`                      |
| Password | `<aws-secret-key>`                      |
| Region   | `<aws-region>`                          |

### Credential Notes

- **Access Key:** AWS IAM user access key (typically 20 characters)
- **Secret Key:** AWS IAM user secret key (can be 40-44+ characters - all supported)
- **Region:** AWS region where SES is enabled (e.g., `us-east-1`, `eu-west-1`)
- **Special Characters:** If your secret key contains `+`, `/`, `=`, etc., URL-encode it

## DEBUGGING & TESTING

Use the enhanced debug command to test and troubleshoot your configuration:

```bash
# Basic configuration analysis
php bin/console mautic:amazon-ses:debug

# Test real AWS SES connection
php bin/console mautic:amazon-ses:debug --test-connection

# Send a test email
php bin/console mautic:amazon-ses:debug --test-email=your-email@domain.com

# Complete test with connection and email
php bin/console mautic:amazon-ses:debug --test-connection --test-email=your-email@domain.com --from=sender@your-verified-domain.com
```

### Debug Features

The debug command provides:
- ✅ **DSN Validation:** Checks both `ses+api` and `ses+smtp` configurations
- ✅ **AWS Connection Test:** Real connection to AWS SES with quota information
- ✅ **Credential Validation:** Flexible validation for various key lengths
- ✅ **Network Connectivity:** Tests connection to AWS endpoints
- ✅ **Email Testing:** Sends actual test emails through your configuration
- ✅ **Error Diagnosis:** Specific troubleshooting for common issues

## AWS SNS CONFIGURATION

To process bounces and complaints, configure AWS SNS:

1. **Create SNS Topic:** Attach to your SES identity
2. **Configure Subscription:**
   - Protocol: `HTTPS`
   - **Enable raw message delivery**
   - Endpoint: `https://your-mautic-domain.com/mailer/callback`
3. **Confirm Subscription:** The plugin will automatically confirm SNS subscriptions

## TROUBLESHOOTING

### Common Issues & Solutions

**1. InvalidSignatureException**
- Usually caused by special characters in secret key
- Solution: URL-encode your secret key in the DSN
- Run debug command for automatic analysis

**2. MessageRejected Error**
- Causes: Unverified sender address, sandbox mode, quota exceeded
- Solution: Verify sender address in AWS SES console

**3. Secret Key Length Warning**
- Your 44-character secret keys are fully supported
- No action needed - this is normal for some AWS configurations

**4. Connection Timeouts**
- Check network connectivity to AWS endpoints
- Verify firewall allows outbound HTTPS traffic

### Secret Key Encoding Example

If your secret key contains special characters:

```php
// Original (may cause issues)
ses+api://AKIAIOSFODNN7:wJalrXUt/K7MDENG+bPxRfi@default?region=us-east-1

// URL-encoded (recommended)
ses+api://AKIAIOSFODNN7:wJalrXUt%2FK7MDENG%2BbPxRfi@default?region=us-east-1
```

## REQUIREMENTS

- Mautic 6.0+
- PHP 8.1+
- AWS SES account with verified domain/email
- Symfony Amazon SES Bridge (for `ses+api` scheme)

## DEVELOPMENT

This plugin follows Mautic 6 plugin architecture and includes:
- Event subscribers for webhook processing
- Console commands for debugging
- Service classes for bounce/complaint handling
- Comprehensive logging for troubleshooting

## AUTHOR

👤 **Enhanced by Development Team**

Original concept by Pablo Veintimilla - Enhanced for Mautic 6 with improved debugging and multi-scheme support. 