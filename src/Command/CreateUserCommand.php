<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:user:create',
    description: 'Erstellt einen neuen Admin-Benutzer',
)]
class CreateUserCommand extends AbstractUserCommand
{
    public function __construct(
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct($entityManager, $passwordHasher);
    }

    /**
     * Erstellt einen neuen Benutzer.
     */
    protected function handle(SymfonyStyle $io, string $email, string $password): int
    {
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            $io->warning('Benutzer mit dieser E-Mail-Adresse existiert bereits.');
            return Command::FAILURE;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setRoles(['ROLE_ADMIN']);

        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('Admin-Benutzer "%s" wurde erfolgreich erstellt.', $email));

        return Command::SUCCESS;
    }
}
