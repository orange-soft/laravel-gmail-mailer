<?php

namespace Orangesoft\GmailMailer\Services;

use Google\Client;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use Illuminate\Support\Facades\Log;
use Orangesoft\GmailMailer\Exceptions\GmailMailerException;

class GmailService
{
    private Client $client;
    private Gmail $service;
    private string $credentialsPath;
    private string $tokenPath;
    private bool $loggingEnabled;
    private ?string $logChannel;

    public function __construct(?string $credentialsPath = null, ?string $tokenPath = null)
    {
        $this->credentialsPath = $credentialsPath ?? config('gmail-mailer.credentials_path');
        $this->tokenPath = $tokenPath ?? config('gmail-mailer.token_path');
        $this->loggingEnabled = config('gmail-mailer.logging.enabled', false);
        $this->logChannel = config('gmail-mailer.logging.channel');

        $this->initializeClient();
    }

    /**
     * Initialize the Google Client and Gmail service.
     *
     * @throws GmailMailerException
     */
    protected function initializeClient(): void
    {
        if (!file_exists($this->credentialsPath)) {
            throw GmailMailerException::credentialsNotFound($this->credentialsPath);
        }

        $this->client = new Client();
        $this->client->setApplicationName(config('gmail-mailer.application_name', 'Laravel'));
        $this->client->setScopes([Gmail::GMAIL_SEND]);
        $this->client->setAuthConfig($this->credentialsPath);
        $this->client->setAccessType('offline');

        if (!file_exists($this->tokenPath)) {
            throw GmailMailerException::tokenNotFound($this->tokenPath);
        }

        $accessToken = json_decode(file_get_contents($this->tokenPath), true);
        $this->client->setAccessToken($accessToken);

        // Refresh token if expired
        if ($this->client->isAccessTokenExpired()) {
            if ($this->client->getRefreshToken()) {
                $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
                $this->saveToken($this->client->getAccessToken());
                $this->log('info', 'Gmail OAuth token refreshed successfully');
            } else {
                throw GmailMailerException::tokenExpired();
            }
        }

        $this->service = new Gmail($this->client);
    }

    /**
     * Send an email using Gmail API.
     *
     * @param string|array $to Recipient email address(es)
     * @param string $subject Email subject
     * @param string $body Email body (HTML content)
     * @param array $options Optional parameters (from, fromName, replyTo, cc, bcc)
     * @return bool
     * @throws GmailMailerException
     */
    public function sendEmail(string|array $to, string $subject, string $body, array $options = []): bool
    {
        try {
            $message = $this->createMessage($to, $subject, $body, $options);
            $this->service->users_messages->send('me', $message);

            $recipients = is_array($to) ? implode(', ', $to) : $to;
            $this->log('info', "Email sent successfully via Gmail API", [
                'to' => $recipients,
                'subject' => $subject,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->log('error', 'Gmail send error: ' . $e->getMessage(), [
                'to' => is_array($to) ? implode(', ', $to) : $to,
                'subject' => $subject,
            ]);

            throw GmailMailerException::sendFailed($e->getMessage());
        }
    }

    /**
     * Create a Gmail Message from email components.
     */
    protected function createMessage(string|array $to, string $subject, string $body, array $options = []): Message
    {
        $boundary = uniqid('boundary_');

        // Build email headers
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        $headers[] = 'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=';

        // From address
        $from = $options['from'] ?? config('mail.from.address');
        $fromName = $options['fromName'] ?? config('mail.from.name');
        if ($fromName) {
            $headers[] = 'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>';
        } else {
            $headers[] = 'From: ' . $from;
        }

        // Add Reply-To if from address differs
        if (empty($options['replyTo']) && $from !== 'me') {
            $headers[] = 'Reply-To: ' . $from;
        }

        // To addresses
        if (is_array($to)) {
            $headers[] = 'To: ' . implode(', ', $to);
        } else {
            $headers[] = 'To: ' . $to;
        }

        // Optional headers
        if (!empty($options['replyTo'])) {
            $headers[] = 'Reply-To: ' . $options['replyTo'];
        }
        if (!empty($options['cc'])) {
            $headers[] = 'Cc: ' . (is_array($options['cc']) ? implode(', ', $options['cc']) : $options['cc']);
        }
        if (!empty($options['bcc'])) {
            $headers[] = 'Bcc: ' . (is_array($options['bcc']) ? implode(', ', $options['bcc']) : $options['bcc']);
        }

        // Build message body (multipart: plain text + HTML)
        $messageParts = [];
        $messageParts[] = '--' . $boundary;
        $messageParts[] = 'Content-Type: text/plain; charset=UTF-8';
        $messageParts[] = 'Content-Transfer-Encoding: base64';
        $messageParts[] = '';
        $messageParts[] = base64_encode(strip_tags($body));
        $messageParts[] = '';
        $messageParts[] = '--' . $boundary;
        $messageParts[] = 'Content-Type: text/html; charset=UTF-8';
        $messageParts[] = 'Content-Transfer-Encoding: base64';
        $messageParts[] = '';
        $messageParts[] = base64_encode($body);
        $messageParts[] = '';
        $messageParts[] = '--' . $boundary . '--';

        // Combine headers and body
        $rawMessage = implode("\r\n", $headers) . "\r\n\r\n" . implode("\r\n", $messageParts);

        // Create Gmail Message
        $message = new Message();
        $message->setRaw($this->base64UrlEncode($rawMessage));

        return $message;
    }

    /**
     * Base64 URL-safe encode for Gmail API.
     */
    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Save the OAuth token to file.
     */
    protected function saveToken(array $token): void
    {
        $dir = dirname($this->tokenPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        file_put_contents($this->tokenPath, json_encode($token));
        chmod($this->tokenPath, 0600);
    }

    /**
     * Log a message if logging is enabled.
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        if (!$this->loggingEnabled) {
            return;
        }

        if ($this->logChannel) {
            Log::channel($this->logChannel)->$level("[GmailMailer] {$message}", $context);
        } else {
            Log::$level("[GmailMailer] {$message}", $context);
        }
    }

    /**
     * Get the Google Client instance.
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * Get the Gmail Service instance.
     */
    public function getGmailService(): Gmail
    {
        return $this->service;
    }

    /**
     * Check if the current token is valid.
     */
    public function isTokenValid(): bool
    {
        return !$this->client->isAccessTokenExpired();
    }

    /**
     * Get the authenticated user's email address.
     */
    public function getAuthenticatedEmail(): ?string
    {
        try {
            $profile = $this->service->users->getProfile('me');
            return $profile->getEmailAddress();
        } catch (\Exception $e) {
            return null;
        }
    }
}
