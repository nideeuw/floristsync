<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\MessageConverter;

/**
 * Sends mail through a Google Apps Script web app (HTTPS), because some hosts
 * (for example Render free plan) block outbound SMTP ports.
 */
class GmailScriptTransport extends AbstractTransport
{
    public function __construct(private string $url, private string $secret)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        foreach ($email->getTo() as $recipient) {
            $response = Http::timeout(20)->asJson()->post($this->url, [
                'secret' => $this->secret,
                'to' => $recipient->getAddress(),
                'subject' => $email->getSubject(),
                'text' => $email->getTextBody(),
                'html' => $email->getHtmlBody(),
                'fromName' => config('mail.from.name'),
            ]);

            if (! $response->successful() || $response->json('ok') !== true) {
                throw new RuntimeException('Gmail relay failed: ' . ($response->json('error') ?? $response->status()));
            }
        }
    }

    public function __toString(): string
    {
        return 'gmailscript';
    }
}