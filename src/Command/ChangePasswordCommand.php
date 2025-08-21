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
     * Ändert das Passwort eines vorhandenen Benutzers anhand der E-Mail und speichert die Änderung in der Datenbank.
     *
     * @param SymfonyStyle $io Konsolen-IO für Ausgaben und Eingaben
     * @param string $email E-Mail des Benutzers, dessen Passwort geändert werden soll
     * @param string $password Neues Klartextpasswort, wird vor dem Speichern gehasht
     */
    protected function handle(SymfonyStyle $io, string $email, string $password): int
    {
        // Benutzer anhand der E-Mail aus der Datenbank laden
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        // Falls kein Benutzer gefunden wurde, eine Fehlermeldung ausgeben und mit Fehlercode beenden
        if (!$user) {
            $io->error('Benutzer mit dieser E-Mail-Adresse wurde nicht gefunden.');
            return Command::FAILURE;
        }

        // Neues Passwort hashen (unter Verwendung des konfigurierten Hashers für den User)
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        // Änderungen in der Datenbank persistieren
        $this->entityManager->flush();

        // Erfolgsmeldung in der Konsole ausgeben
        $io->success(sprintf('Passwort für Benutzer "%s" wurde erfolgreich geändert.', $email));

        return Command::SUCCESS;
    }
}
