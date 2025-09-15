<?php

namespace App\Entity;

use App\ValueObject\EmailAddress;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'email_settings')]
class EmailSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    /** @phpstan-ignore-next-line */
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $host = 'localhost';

    #[ORM\Column(type: 'integer')]
    private int $port = 25;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $username = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $password = null;

    #[ORM\Column(type: 'boolean')]
    private bool $ignoreSsl = false;

    #[ORM\Column(type: 'string', length: 255)]
    private string $senderEmail = 'noreply@besteller.local';

    private ?EmailAddress $senderEmailAddress = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function setHost(string $host): static
    {
        $this->host = $host;
        return $this;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function setPort(int $port): static
    {
        $this->port = $port;
        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): static
    {
        $this->username = $username;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function isIgnoreSsl(): bool
    {
        return $this->ignoreSsl;
    }

    public function setIgnoreSsl(bool $ignoreSsl): static
    {
        $this->ignoreSsl = $ignoreSsl;
        return $this;
    }

    public function getSenderEmail(): string
    {
        return $this->senderEmail;
    }

    public function setSenderEmail(string $senderEmail): static
    {
        $this->senderEmail = $senderEmail;
        $this->senderEmailAddress = new EmailAddress($senderEmail);
        return $this;
    }

    public function getSenderEmailAddress(): EmailAddress
    {
        if ($this->senderEmailAddress === null) {
            $this->senderEmailAddress = new EmailAddress($this->senderEmail);
        }
        return $this->senderEmailAddress;
    }

    public function setSenderEmailAddress(EmailAddress $senderEmailAddress): static
    {
        $this->senderEmailAddress = $senderEmailAddress;
        $this->senderEmail = $senderEmailAddress->getValue();
        return $this;
    }
}
