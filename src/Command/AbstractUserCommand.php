<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Basisklasse für Benutzerbefehle mit gemeinsamen Argumenten und Validierung.
 */
abstract class AbstractUserCommand extends Command
{
    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    /**
     * Konfiguriert gemeinsame Argumente für Benutzerbefehle.
     */
    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'E-Mail-Adresse des Benutzers')
            ->addArgument('password', InputArgument::REQUIRED, 'Passwort (mindestens 16 Zeichen)');
    }

    /**
     * Führt die gemeinsame Validierung aus und delegiert die Verarbeitung.
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

        if (strlen($password) < 16) {
            $io->error('Das Passwort muss mindestens 16 Zeichen lang sein.');
            return Command::FAILURE;
        }

        return $this->handle($io, $email, $password);
    }

    /**
     * Führt die befehlspezifische Logik aus.
     */
    abstract protected function handle(SymfonyStyle $io, string $email, string $password): int;
}

