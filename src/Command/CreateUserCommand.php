<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:user:create',
    description: 'Erstellt einen neuen Admin-Benutzer',
)]
class CreateUserCommand extends Command
{
    /**
     * @param EntityManagerInterface      $entityManager Datenbankzugriff
     * @param UserPasswordHasherInterface $passwordHasher Dienst zum Hashen des Passworts
     */
    // Konfiguriert den Befehl, legt Name, Beschreibung und Argumente fest
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    /**
     * Definiert die erwarteten Argumente für den Konsolenbefehl.
     */
    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'E-Mail-Adresse des Benutzers')
            ->addArgument('password', InputArgument::REQUIRED, 'Passwort (mindestens 16 Zeichen)');
    // Führt den Befehl aus, erstellt und speichert einen neuen Benutzer
    }

    /**
     * Führt den Befehl aus und erstellt den Benutzer.
     *
     * @param InputInterface  $input  Eingabeparameter der Konsole
     * @param OutputInterface $output Ausgabeschnittstelle
     *
     * @return int Statuscode
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        $password = $input->getArgument('password');

        if (!is_string($email) || !is_string($password)) {
            $io->error('Ungültige Eingabewerte.');
            return Command::FAILURE;
        }

        // Passwort-Validierung
        if (strlen($password) < 16) {
            $io->error('Das Passwort muss mindestens 16 Zeichen lang sein.');
            return Command::FAILURE;
        }

        // Prüfen ob Benutzer bereits existiert
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            $io->warning('Benutzer mit dieser E-Mail-Adresse existiert bereits.');
            return Command::FAILURE;
        }

        // Benutzer erstellen
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
