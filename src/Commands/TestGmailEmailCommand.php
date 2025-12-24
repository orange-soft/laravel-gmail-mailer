<?php

namespace Orangesoft\GmailMailer\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Orangesoft\GmailMailer\Services\GmailService;

class TestGmailEmailCommand extends Command
{
    protected $signature = 'os:gmail-mailer:test
                            {email? : The email address to send the test email to}
                            {--subject= : Custom subject line}
                            {--check-only : Only check the OAuth token status without sending}';

    protected $description = 'Send a test email via Gmail API to verify the setup';

    public function handle(): int
    {
        // Check token status first
        if (!$this->checkTokenStatus()) {
            return Command::FAILURE;
        }

        if ($this->option('check-only')) {
            return Command::SUCCESS;
        }

        $email = $this->argument('email')
            ?? config('gmail-mailer.test_recipient')
            ?? $this->ask('Enter the email address to send the test email to');

        if (empty($email)) {
            $this->error('Email address is required.');
            $this->info('You can set a default test recipient in your .env file:');
            $this->line('GMAIL_TEST_RECIPIENT=your@email.com');

            return Command::FAILURE;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email address format.');

            return Command::FAILURE;
        }

        $subject = $this->option('subject') ?? 'Gmail Mailer Test - ' . config('app.name', 'Laravel');

        $this->info("Sending test email to: {$email}");

        try {
            $gmailService = new GmailService();

            $body = $this->getTestEmailBody();

            $gmailService->sendEmail($email, $subject, $body, [
                'from' => config('mail.from.address'),
                'fromName' => config('mail.from.name'),
            ]);

            $this->line('');
            $this->info('Test email sent successfully!');
            $this->info("Check {$email} for the test message.");
            $this->line('');
            $this->info('If you received the email, your Gmail OAuth2 setup is working correctly.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to send test email: ' . $e->getMessage());
            $this->line('');
            $this->info('Troubleshooting steps:');
            $this->line('1. Run: php artisan os:gmail-mailer:setup to re-authenticate');
            $this->line('2. Check that MAIL_MAILER=gmail-oauth2 in your .env file');
            $this->line('3. Verify the Gmail API is enabled in Google Cloud Console');

            return Command::FAILURE;
        }
    }

    /**
     * Check the OAuth token status.
     */
    protected function checkTokenStatus(): bool
    {
        $credentialsPath = config('gmail-mailer.credentials_path');
        $tokenPath = config('gmail-mailer.token_path');

        $this->info('Checking Gmail OAuth2 configuration...');
        $this->line('');

        // Check credentials file
        if (!file_exists($credentialsPath)) {
            $this->error("Credentials file not found at: {$credentialsPath}");
            $this->info('Run: php artisan os:gmail-mailer:setup');

            return false;
        }
        $this->line("  [OK] Credentials file found");

        // Check token file
        if (!file_exists($tokenPath)) {
            $this->error("Token file not found at: {$tokenPath}");
            $this->info('Run: php artisan os:gmail-mailer:setup');

            return false;
        }
        $this->line("  [OK] Token file found");

        // Check token validity
        try {
            $gmailService = new GmailService();

            if ($gmailService->isTokenValid()) {
                $this->line("  [OK] Token is valid");
            } else {
                $this->warn("  [WARN] Token was expired but has been refreshed");
            }

            $authenticatedEmail = $gmailService->getAuthenticatedEmail();
            if ($authenticatedEmail) {
                $this->line("  [OK] Authenticated as: {$authenticatedEmail}");
            }
        } catch (\Exception $e) {
            $this->error("Token validation failed: {$e->getMessage()}");
            $this->info('Run: php artisan os:gmail-mailer:setup');

            return false;
        }

        $this->line('');

        return true;
    }

    /**
     * Generate the test email body.
     */
    protected function getTestEmailBody(): string
    {
        $appName = config('app.name', 'Laravel');
        $timestamp = now()->format('Y-m-d H:i:s T');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #4285f4 0%, #34a853 100%);
            color: white;
            padding: 30px;
            border-radius: 8px 8px 0 0;
            text-align: center;
        }
        .content {
            background: #f9f9f9;
            padding: 30px;
            border-radius: 0 0 8px 8px;
        }
        .success-badge {
            display: inline-block;
            background: #34a853;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            margin-bottom: 20px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .info-table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        .info-table td:first-child {
            color: #666;
            width: 40%;
        }
        .footer {
            text-align: center;
            color: #666;
            font-size: 12px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Gmail OAuth2 Test</h1>
    </div>
    <div class="content">
        <span class="success-badge">Success!</span>
        <p>This is a test email sent via the <strong>Gmail API</strong> using OAuth2 authentication.</p>
        <p>If you're reading this, your Gmail mailer configuration is working correctly!</p>

        <table class="info-table">
            <tr>
                <td>Application</td>
                <td><strong>{$appName}</strong></td>
            </tr>
            <tr>
                <td>Sent at</td>
                <td>{$timestamp}</td>
            </tr>
            <tr>
                <td>Transport</td>
                <td>gmail-oauth2</td>
            </tr>
        </table>

        <p>You can now use Laravel's Mail facade to send emails through Gmail.</p>
    </div>
    <div class="footer">
        <p>Sent by orangesoft/laravel-gmail-mailer</p>
    </div>
</body>
</html>
HTML;
    }
}
