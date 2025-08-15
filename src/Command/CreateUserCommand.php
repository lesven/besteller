<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:user:create',
    description: 'Erstellt einen neuen Benutzer (standardmäßig Admin, optional Rolle)',
)]
class CreateUserCommand extends AbstractUserCommand
{
    protected static $defaultName = 'app:user:create';

    protected function configure(): void
    {
        parent::configure();
        $this->addOption('role', null, InputOption::VALUE_REQUIRED, 'Role to assign (ROLE_ADMIN|ROLE_EDITOR|ROLE_SENDER)', 'ROLE_ADMIN');
    }
    // Konstruktor vom AbstractUserCommand wird verwendet

    /**
     * Erstellt einen neuen Benutzer.
     */
    protected function handle(SymfonyStyle $io, string $email, string $password, InputInterface $input): int
    {
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            $io->warning('Benutzer mit dieser E-Mail-Adresse existiert bereits.');
            return Command::FAILURE;
        }


    // Rolle aus Input-Option lesen
    $role = (string) $input->getOption('role');
        $allowed = ['ROLE_ADMIN', 'ROLE_EDITOR', 'ROLE_SENDER'];
        if (!in_array($role, $allowed, true)) {
            $io->error('Ungültige Rolle. Erlaubt: ' . implode(', ', $allowed));
            return Command::FAILURE;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setRoles([$role]);

    $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
    $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('Benutzer "%s" mit Rolle "%s" wurde erfolgreich erstellt.', $email, $role));

        return Command::SUCCESS;
    }
}
