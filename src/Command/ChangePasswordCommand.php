<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:user:change-password',
    description: 'Ändert das Passwort eines bestehenden Benutzers',
)]
class ChangePasswordCommand extends AbstractUserCommand
{
    public function __construct(
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct($entityManager, $passwordHasher);
    }

    /**
     * Ändert das Passwort eines vorhandenen Benutzers.
     */
    protected function handle(SymfonyStyle $io, string $email, string $password): int
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user) {
            $io->error('Benutzer mit dieser E-Mail-Adresse wurde nicht gefunden.');
            return Command::FAILURE;
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $this->entityManager->flush();

        $io->success(sprintf('Passwort für Benutzer "%s" wurde erfolgreich geändert.', $email));

        return Command::SUCCESS;
    }
}
