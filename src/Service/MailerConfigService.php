<?php

namespace App\Service;

use App\Entity\EmailSettings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;

class MailerConfigService
{
    public function __construct(
        private MailerInterface $defaultMailer,
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Gets email settings from database
     */
    public function getEmailSettings(): ?EmailSettings
    {
        return $this->entityManager->getRepository(EmailSettings::class)->find(1);
    }

    /**
     * Gets configured mailer instance based on settings
     */
    public function getConfiguredMailer(): MailerInterface
    {
        $settings = $this->getEmailSettings();
        if (!$settings) {
            return $this->defaultMailer;
        }

        return new Mailer(Transport::fromDsn($this->buildDsn($settings)));
    }

    /**
     * Gets sender email from settings or default
     */
    public function getSenderEmail(): string
    {
        $settings = $this->getEmailSettings();
        return $settings?->getSenderEmail() ?? 'noreply@besteller.local';
    }

    /**
     * Builds DSN string from email settings
     */
    private function buildDsn(EmailSettings $settings): string
    {
        $dsn = 'smtp://';
        
        if ($settings->getUsername()) {
            $dsn .= rawurlencode($settings->getUsername());
            if ($settings->getPassword()) {
                $dsn .= ':' . rawurlencode($settings->getPassword());
            }
            $dsn .= '@';
        }
        
        $dsn .= $settings->getHost() . ':' . $settings->getPort();
        
        if ($settings->isIgnoreSsl()) {
            $dsn .= '?verify_peer=0';
        }

        return $dsn;
    }
}