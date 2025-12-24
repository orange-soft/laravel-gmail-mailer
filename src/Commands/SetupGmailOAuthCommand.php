<?php

namespace Orangesoft\GmailMailer\Commands;

use Google\Client;
use Google\Service\Gmail;
use Illuminate\Console\Command;
use Orangesoft\GmailMailer\Exceptions\GmailMailerException;

class SetupGmailOAuthCommand extends Command
{
    protected $signature = 'os:gmail-mailer:setup
                            {--credentials= : Path to the credentials.json file}
                            {--token= : Path to save the token.json file}';

    protected $description = 'Setup Gmail OAuth2 authentication for sending emails';

    public function handle(): int
    {
        $credentialsPath = $this->option('credentials') ?? config('gmail-mailer.credentials_path');
        $tokenPath = $this->option('token') ?? config('gmail-mailer.token_path');

        if (!file_exists($credentialsPath)) {
            $this->error("Credentials file not found at: {$credentialsPath}");
            $this->line('');
            $this->info('To get your credentials:');
            $this->line('1. Go to Google Cloud Console: https://console.cloud.google.com/');
            $this->line('2. Create a new project or select an existing one');
            $this->line('3. Enable the Gmail API');
            $this->line('4. Go to Credentials > Create Credentials > OAuth client ID');
            $this->line('5. Select "Desktop app" as the application type');
            $this->line('6. Download the credentials JSON file');
            $this->line("7. Save it to: {$credentialsPath}");

            return Command::FAILURE;
        }

        $client = new Client();
        $client->setApplicationName(config('gmail-mailer.application_name', 'Laravel'));
        $client->setScopes([Gmail::GMAIL_SEND]);
        $client->setAuthConfig($credentialsPath);
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');

        // Use the copy/paste flow
        $client->setRedirectUri('urn:ietf:wg:oauth:2.0:oob');

        // Check if token already exists and is valid
        if (file_exists($tokenPath)) {
            $accessToken = json_decode(file_get_contents($tokenPath), true);
            $client->setAccessToken($accessToken);

            if (!$client->isAccessTokenExpired()) {
                $this->info('Gmail OAuth2 is already set up and token is valid!');
                $this->displayAuthenticatedEmail($client);

                return Command::SUCCESS;
            }

            // Try to refresh the token
            if ($client->getRefreshToken()) {
                try {
                    $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                    $this->saveToken($tokenPath, $client->getAccessToken());
                    $this->info('Token refreshed successfully!');
                    $this->displayAuthenticatedEmail($client);

                    return Command::SUCCESS;
                } catch (\Exception $e) {
                    $this->warn('Failed to refresh token. Re-authenticating...');
                }
            }
        }

        // Need to authenticate
        $authUrl = $client->createAuthUrl();

        $this->line('');
        $this->info('=== Gmail OAuth2 Setup ===');
        $this->line('');
        $this->info('1. Open the following link in your browser:');
        $this->line('');
        $this->line($authUrl);
        $this->line('');
        $this->info('2. Log in with your Google account and grant permissions');
        $this->info('3. You\'ll see an authorization code on the screen');
        $this->info('4. Copy and paste that code below');
        $this->line('');

        $authCode = $this->ask('Enter the authorization code (only the code value, not the entire URL)');

        if (empty($authCode)) {
            $this->error('Authorization code is required.');

            return Command::FAILURE;
        }

        // Clean up the code in case user pastes extra characters
        $authCode = trim($authCode);

        // Check if user accidentally pasted the whole URL
        if (str_contains($authCode, 'code=')) {
            parse_str(parse_url($authCode, PHP_URL_QUERY), $params);
            $authCode = $params['code'] ?? $authCode;
        }

        try {
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
            $client->setAccessToken($accessToken);

            if (array_key_exists('error', $accessToken)) {
                $this->error('Error: ' . implode(', ', $accessToken));

                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('Failed to authenticate: ' . $e->getMessage());
            $this->info('Make sure you copied only the code value, not the entire URL');

            return Command::FAILURE;
        }

        // Save the token
        $this->saveToken($tokenPath, $client->getAccessToken());

        $this->line('');
        $this->info('Gmail OAuth2 setup completed successfully!');
        $this->info("Token saved to: {$tokenPath}");
        $this->displayAuthenticatedEmail($client);
        $this->line('');
        $this->info('Your app now has permission to send emails via Gmail API');
        $this->line('');
        $this->info('Next steps:');
        $this->line('1. Set MAIL_MAILER=gmail-oauth2 in your .env file');
        $this->line('2. Run: php artisan os:gmail-mailer:test to verify the setup');

        return Command::SUCCESS;
    }

    /**
     * Save the OAuth token to file.
     */
    protected function saveToken(string $tokenPath, array $token): void
    {
        $dir = dirname($tokenPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        file_put_contents($tokenPath, json_encode($token));
        chmod($tokenPath, 0600);
    }

    /**
     * Display the authenticated email address.
     */
    protected function displayAuthenticatedEmail(Client $client): void
    {
        try {
            $gmail = new Gmail($client);
            $profile = $gmail->users->getProfile('me');
            $this->info('Authenticated as: ' . $profile->getEmailAddress());
        } catch (\Exception $e) {
            // Silently ignore if we can't get the email
        }
    }
}
