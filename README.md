# Laravel Gmail Mailer

A Laravel mail transport using Gmail API with OAuth2 authentication. This package provides a secure alternative to SMTP for sending emails through Gmail.

## Requirements

- PHP 8.0.2+
- Laravel 9.0, 10.0, 11.0, or 12.0
- A Google Cloud project with Gmail API enabled

## Installation

Install the package via Composer:

```bash
composer require orange-soft/laravel-gmail-mailer
```

The package will auto-register its service provider.

### Publish Configuration

```bash
php artisan vendor:publish --tag=gmail-mailer-config
```

## Google Cloud Setup

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Enable the **Gmail API**:
   - Navigate to "APIs & Services" > "Library"
   - Search for "Gmail API" and enable it
4. Create OAuth2 credentials:
   - Go to "APIs & Services" > "Credentials"
   - Click "Create Credentials" > "OAuth client ID"
   - Select "Desktop app" as the application type
   - Download the credentials JSON file
5. Save the credentials file:
   - Default location: `storage/app/gmail/credentials.json`
   - Or set a custom path via `GMAIL_CREDENTIALS_PATH`

## Setup OAuth Token

Run the setup command to authenticate with your Google account:

```bash
php artisan os:gmail-mailer:setup
```

This will:
1. Display an authorization URL
2. Ask you to open the URL in your browser
3. Prompt you to enter the authorization code
4. Save the OAuth token for future use

## Configuration

### Environment Variables

Add to your `.env` file:

```env
MAIL_MAILER=gmail-oauth2

# Optional: Custom paths for credentials and token
GMAIL_CREDENTIALS_PATH=/path/to/credentials.json
GMAIL_TOKEN_PATH=/path/to/token.json

# Optional: Application name shown in Google OAuth consent
GMAIL_APP_NAME="My Application"

# Optional: Default test recipient for the test command
GMAIL_TEST_RECIPIENT=test@example.com

# Optional: Logging
GMAIL_LOGGING_ENABLED=true
GMAIL_LOG_CHANNEL=stack
```

### Mail Configuration

Add the Gmail mailer to your `config/mail.php`:

```php
'mailers' => [
    // ... other mailers

    'gmail-oauth2' => [
        'transport' => 'gmail-oauth2',
    ],
],
```

## Usage

### Send Email via Laravel Mail Facade

```php
use Illuminate\Support\Facades\Mail;
use App\Mail\WelcomeEmail;

// Send using the default mailer (when MAIL_MAILER=gmail-oauth2)
Mail::to('user@example.com')->send(new WelcomeEmail());

// Or explicitly use the Gmail mailer
Mail::mailer('gmail-oauth2')
    ->to('user@example.com')
    ->send(new WelcomeEmail());
```

### Direct Service Usage

```php
use Orangesoft\GmailMailer\Services\GmailService;

$gmail = app(GmailService::class);

$gmail->sendEmail(
    to: 'recipient@example.com',
    subject: 'Hello World',
    body: '<h1>Welcome!</h1><p>This is a test email.</p>',
    options: [
        'from' => 'sender@gmail.com',
        'fromName' => 'My App',
        'cc' => ['cc@example.com'],
        'bcc' => ['bcc@example.com'],
        'replyTo' => 'reply@example.com',
    ]
);
```

## Commands

### Setup OAuth

```bash
php artisan os:gmail-mailer:setup

# With custom paths
php artisan os:gmail-mailer:setup --credentials=/path/to/credentials.json --token=/path/to/token.json
```

### Test Email

```bash
# Send a test email
php artisan os:gmail-mailer:test user@example.com

# With custom subject
php artisan os:gmail-mailer:test user@example.com --subject="Custom Subject"

# Check token status only (no email sent)
php artisan os:gmail-mailer:test --check-only
```

## Token Refresh

The package automatically refreshes expired OAuth tokens. If the refresh token becomes invalid, re-run:

```bash
php artisan os:gmail-mailer:setup
```

## Troubleshooting

### Token Expired Error

If you see "Gmail OAuth token has expired and cannot be refreshed", run:

```bash
php artisan os:gmail-mailer:setup
```

### Credentials Not Found

Ensure your credentials file exists at the configured path:

```bash
ls -la storage/app/gmail/credentials.json
```

### Permission Denied

Check file permissions:

```bash
chmod 600 storage/app/gmail/token.json
chmod 600 storage/app/gmail/credentials.json
```

### Check Configuration

Verify your setup:

```bash
php artisan os:gmail-mailer:test --check-only
```

## Security

- OAuth tokens are stored with restricted permissions (0600)
- Credentials and tokens should be added to `.gitignore`
- Never commit credential or token files to version control

Add to your `.gitignore`:

```
storage/app/gmail/credentials.json
storage/app/gmail/token.json
```

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).
