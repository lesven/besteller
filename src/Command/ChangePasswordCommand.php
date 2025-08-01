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
    name: 'app:user:change-password',
    description: 'Ändert das Passwort eines bestehenden Benutzers',
)]
class ChangePasswordCommand extends Command
{
    /**
     * @param EntityManagerInterface      $entityManager Datenbankzugriff
     * @param UserPasswordHasherInterface $passwordHasher Passwort-Hasher
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    /**
     * Legt die benötigten Argumente für den Befehl fest.
     */
    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'E-Mail-Adresse des Benutzers')
            ->addArgument('password', InputArgument::REQUIRED, 'Neues Passwort (mindestens 16 Zeichen)');
    }

    /**
     * Ändert das Passwort eines vorhandenen Benutzers.
     *
     * @param InputInterface  $input  Eingabedaten der Konsole
     * @param OutputInterface $output Ausgabeschnittstelle
     *
     * @return int Statuscode
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        if (!is_string($email)) {
            $io->error('Ungültige E-Mail-Adresse.');
            return Command::FAILURE;
        }

        $password = $input->getArgument('password');
        if (!is_string($password)) {
            $io->error('Ungültiges Passwort.');
            return Command::FAILURE;
        }

        // Passwort-Validierung
        if (strlen($password) < 16) {
            $io->error('Das Passwort muss mindestens 16 Zeichen lang sein.');
            return Command::FAILURE;
        }

        // Benutzer suchen
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user) {
            $io->error('Benutzer mit dieser E-Mail-Adresse wurde nicht gefunden.');
            return Command::FAILURE;
        }

        // Passwort ändern
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $this->entityManager->flush();

        $io->success(sprintf('Passwort für Benutzer "%s" wurde erfolgreich geändert.', $email));

        return Command::SUCCESS;
    }
}
