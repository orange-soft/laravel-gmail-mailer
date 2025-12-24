<?php

namespace Orangesoft\GmailMailer\Exceptions;

use Exception;

class GmailMailerException extends Exception
{
    public static function credentialsNotFound(string $path): self
    {
        return new self(
            "Gmail credentials file not found at: {$path}. " .
            "Please download your OAuth2 credentials from Google Cloud Console and place them at the configured path."
        );
    }

    public static function tokenNotFound(string $path): self
    {
        return new self(
            "Gmail OAuth token not found at: {$path}. " .
            "Please run: php artisan os:gmail-mailer:setup"
        );
    }

    public static function tokenExpired(): self
    {
        return new self(
            "Gmail OAuth token has expired and cannot be refreshed. " .
            "Please run: php artisan os:gmail-mailer:setup"
        );
    }

    public static function sendFailed(string $message): self
    {
        return new self("Failed to send email via Gmail API: {$message}");
    }

    public static function authenticationFailed(string $message): self
    {
        return new self("Gmail OAuth authentication failed: {$message}");
    }
}
