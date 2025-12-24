<?php

namespace Orangesoft\GmailMailer\Transport;

use Orangesoft\GmailMailer\Services\GmailService;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\MessageConverter;

class GmailOAuth2Transport extends AbstractTransport
{
    private GmailService $gmailService;

    public function __construct(?string $credentialsPath = null, ?string $tokenPath = null)
    {
        parent::__construct();
        $this->gmailService = new GmailService($credentialsPath, $tokenPath);
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        // Extract TO addresses
        $toAddresses = [];
        foreach ($email->getTo() as $address) {
            $toAddresses[] = $address->getAddress();
        }

        // Get subject and body
        $subject = $email->getSubject() ?? '';
        $body = $email->getHtmlBody() ?? $email->getTextBody() ?? '';

        // Build options array safely
        $options = [];

        // Handle FROM address
        $fromAddresses = $email->getFrom();
        if (!empty($fromAddresses)) {
            foreach ($fromAddresses as $address) {
                $options['from'] = $address->getAddress();
                $options['fromName'] = $address->getName();
                break; // Only use the first from address
            }
        }

        // Use config defaults if from is not set
        if (empty($options['from'])) {
            $options['from'] = config('mail.from.address');
            $options['fromName'] = config('mail.from.name');
        }

        // Handle CC addresses
        $ccAddresses = [];
        foreach ($email->getCc() as $address) {
            $ccAddresses[] = $address->getAddress();
        }
        if (!empty($ccAddresses)) {
            $options['cc'] = $ccAddresses;
        }

        // Handle BCC addresses
        $bccAddresses = [];
        foreach ($email->getBcc() as $address) {
            $bccAddresses[] = $address->getAddress();
        }
        if (!empty($bccAddresses)) {
            $options['bcc'] = $bccAddresses;
        }

        // Handle Reply-To
        $replyToAddresses = $email->getReplyTo();
        if (!empty($replyToAddresses)) {
            foreach ($replyToAddresses as $address) {
                $options['replyTo'] = $address->getAddress();
                break; // Only use the first reply-to address
            }
        }

        // Send the email
        $this->gmailService->sendEmail($toAddresses, $subject, $body, $options);
    }

    /**
     * Get the GmailService instance.
     */
    public function getGmailService(): GmailService
    {
        return $this->gmailService;
    }

    public function __toString(): string
    {
        return 'gmail+oauth2://default';
    }
}
