<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class MailService
{
    public function __construct(
        private MailerInterface $mailer,
        private string $appNoreply,
        private string $appName
    ) {
    }

    public function send(string $to, string $subject, string $htmlBody, ?string $textBody = null): void
    {
        $email = (new Email())
            ->from($this->appNoreply)
            ->to($to)
            ->subject($subject)
            ->html($htmlBody);

        if ($textBody) {
            $email->text($textBody);
        }

        $this->mailer->send($email);
    }

    public function sendToAdmin(string $subject, string $htmlBody, ?string $textBody = null): void
    {
        $this->send($this->appNoreply, $subject, $htmlBody, $textBody);
    }
}
