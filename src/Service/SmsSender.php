<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class SmsSender
{
    public function __construct(private readonly MailerInterface $mailer, private readonly LoggerInterface $logger) {}

    public function send(string $toPhone, string $message): void
    {
        // Dev placeholder: log + send via Mailer to Mailhog as a faux SMS
        $this->logger->info('[SMS] to '.$toPhone.': '.$message);
        try {
            $email = (new Email())
                ->from('sms-gateway@tribuconnect.local')
                ->to('sms@tribuconnect.local')
                ->subject('SMS to '.$toPhone)
                ->text($message);
            $this->mailer->send($email);
        } catch (\Throwable) {
            // ignore failures in dev
        }
    }
}

