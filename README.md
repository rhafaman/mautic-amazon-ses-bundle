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

**1. Connection Timeout Issues (SSL/TLS)**

**Error:** `Connection could not be established with host "ssl://email-smtp.us-east-1.amazonaws.com:465": stream_socket_client(): Unable to connect to ssl://email-smtp.us-east-1.amazonaws.com:465 (Connection timed out)`

**Root Causes & Solutions:**

- **Port 465 SSL Issues:** Amazon SES port 465 can cause timeout issues with some server configurations
  - **Solution:** Plugin automatically switches to port 587 (STARTTLS) for better compatibility
  - **Manual Fix:** Change your DSN to use port 587 instead of 465

- **Firewall/Network Issues:** Your server might be blocking outbound SMTP traffic
  - **Test Connectivity:** Use the debug command: `php bin/console mautic:amazon-ses:debug --test-smtp-connectivity`
  - **Solution:** Contact your hosting provider to ensure ports 587 and/or 465 are open for outbound traffic

- **Shared Hosting Restrictions:** Many shared hosts block standard SMTP ports
  - **Solution:** Some hosts provide alternative ports like 2587 for Amazon SES
  - **Alternative:** Use `ses+api` scheme instead of `ses+smtp` for better compatibility

**2. SSL/TLS Configuration Issues**

The plugin now includes enhanced SSL/TLS configuration with:
- Improved cipher suites for Amazon SES compatibility
- Proper SNI (Server Name Indication) configuration
- Connection timeout settings to prevent hanging
- Automatic fallback from port 465 to 587

**3. Recommended Configuration for ses+smtp:**

```
Scheme: ses+smtp
Host: email-smtp.us-east-1.amazonaws.com
Port: 587 (recommended) or 465
Encryption: STARTTLS (port 587) or SSL (port 465)
Auth: Login
```

**4. Alternative Configuration using ses+api (Recommended):**

```
Scheme: ses+api
Host: default
Port: 465
User: <aws-access-key>
Password: <aws-secret-key>
Region: <aws-region>
```

**2. InvalidSignatureException**
- Usually caused by special characters in secret key
- Solution: URL-encode your secret key in the DSN
- Run debug command for automatic analysis

**3. MessageRejected Error**
- Causes: Unverified sender address, sandbox mode, quota exceeded
- Solution: Verify sender address in AWS SES console

**4. Secret Key Length Warning**
- Your 44-character secret keys are fully supported
- No action needed - this is normal for some AWS configurations

**5. Connection Timeouts**
- Check network connectivity to AWS endpoints
- Verify firewall allows outbound HTTPS traffic
- Use debug command to test connectivity: `php bin/console mautic:amazon-ses:debug --test-smtp-connectivity`

### Enhanced Debug Command

The plugin includes a comprehensive debug command with new connectivity testing:

```bash
# Complete diagnostic with connectivity test
php bin/console mautic:amazon-ses:debug --test-smtp-connectivity --test-connection

# Test SMTP connectivity only
php bin/console mautic:amazon-ses:debug --test-smtp-connectivity

# Send test email with enhanced error diagnostics
php bin/console mautic:amazon-ses:debug --test-email=your-email@domain.com --from=sender@your-verified-domain.com
```

### Port Recommendations

Based on community feedback and AWS documentation:

1. **Port 587 (STARTTLS) - RECOMMENDED**
   - Better compatibility with hosting providers
   - Less likely to be blocked by firewalls
   - Supported by most email clients and servers

2. **Port 465 (SSL)**
   - Can cause timeout issues on some configurations
   - Plugin automatically tries port 587 if 465 fails
   - Use only if specifically required

3. **Alternative Ports**
   - Some hosting providers offer port 2587 for Amazon SES
   - Check with your hosting provider for available ports

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