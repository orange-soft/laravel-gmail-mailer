<?php

namespace Orangesoft\GmailMailer;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Orangesoft\GmailMailer\Commands\SetupGmailOAuthCommand;
use Orangesoft\GmailMailer\Commands\TestGmailEmailCommand;
use Orangesoft\GmailMailer\Services\GmailService;
use Orangesoft\GmailMailer\Transport\GmailOAuth2Transport;

class GmailMailerServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge package config with application config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/gmail-mailer.php',
            'gmail-mailer'
        );

        // Register GmailService as a singleton
        $this->app->singleton(GmailService::class, function ($app) {
            return new GmailService(
                config('gmail-mailer.credentials_path'),
                config('gmail-mailer.token_path')
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Publish config file
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/gmail-mailer.php' => config_path('gmail-mailer.php'),
            ], 'gmail-mailer-config');

            // Register Artisan commands
            $this->commands([
                SetupGmailOAuthCommand::class,
                TestGmailEmailCommand::class,
            ]);
        }

        // Register the Gmail OAuth2 mail transport
        Mail::extend('gmail-oauth2', function (array $config = []) {
            return new GmailOAuth2Transport(
                $config['credentials_path'] ?? config('gmail-mailer.credentials_path'),
                $config['token_path'] ?? config('gmail-mailer.token_path')
            );
        });
    }
}
