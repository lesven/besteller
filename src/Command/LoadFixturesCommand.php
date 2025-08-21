<?php

namespace App\Command;

use App\Entity\Checklist;
use App\Entity\ChecklistGroup;
use App\Entity\GroupItem;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'app:database:fixtures',
    description: 'Lädt Fixture-Daten für IT-Ausstattung in die Datenbank'
)]
class LoadFixturesCommand extends Command
{
    private const CATEGORY_COMPUTERS = 'Computer/Laptops';
    private const CATEGORY_MONITORS = 'Monitore';
    private const CATEGORY_PERIPHERIE = 'Peripherie';
    private const CATEGORY_SOFTWARE = 'Software/Lizenzen';
    private const CATEGORY_MOBILE = 'Mobilgeräte';
    private const CATEGORY_OFFICE = 'Büroausstattung';
    private const OPTION_NOT_NEEDED = 'Nicht benötigt';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ParameterBagInterface $parameterBag
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Prüfung: Nur in der Entwicklungsumgebung ausführen
        if ($this->parameterBag->get('kernel.environment') !== 'dev') {
            $io->error('Dieser Command läuft nur in der Entwicklungsumgebung (APP_ENV=dev)');
            return Command::FAILURE;
        }

        $io->title('Lade IT-Ausstattungs-Fixtures');

        // Bestehende Daten löschen
        $io->section('Lösche bestehende Daten...');
        $this->clearDatabase();
        $io->success('Alle bestehenden Daten wurden gelöscht');

        // Fixture-Daten erstellen
        $io->section('Erstelle Fixture-Daten...');
        $this->createFixtures();
        $io->success('Fixture-Daten wurden erfolgreich erstellt');

        return Command::SUCCESS;
    }

    private function clearDatabase(): void
    {
        // Lösche in der richtigen Reihenfolge wegen Foreign Key Constraints
        $this->entityManager->createQuery('DELETE FROM App\Entity\GroupItem')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\ChecklistGroup')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Submission')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Checklist')->execute();
        
        $this->entityManager->flush();
    }

    private function createFixtures(): void
    {
        // IT/Entwicklung Abteilung
        $itChecklist = $this->createChecklist(
            'IT/Entwicklung - Ausstattung',
            'it@example.com',
            'it-support@example.com'
        );
        $this->createITDepartmentData($itChecklist);

        // Marketing Abteilung
        $marketingChecklist = $this->createChecklist(
            'Marketing - Ausstattung',
            'marketing@example.com',
            'marketing-lead@example.com'
        );
        $this->createMarketingDepartmentData($marketingChecklist);

        // Sonstige Abteilung
        $otherChecklist = $this->createChecklist(
            'Sonstige - Standard Büroausstattung',
            'allgemein@example.com',
            'verwaltung@example.com'
        );
        $this->createOtherDepartmentData($otherChecklist);

        $this->entityManager->flush();
    }

    private function createChecklist(string $title, string $targetEmail, string $replyEmail): Checklist
    {
        $checklist = new Checklist();
        $checklist->setTitle($title);
        $checklist->setTargetEmail($targetEmail);
        $checklist->setReplyEmail($replyEmail);
        $checklist->setEmailTemplate($this->getDefaultEmailTemplate());
        $checklist->setLinkEmailTemplate($this->getDefaultLinkEmailTemplate());
        $checklist->setConfirmationEmailTemplate($this->getDefaultConfirmationEmailTemplate());

        $this->entityManager->persist($checklist);
        return $checklist;
    }

    private function createITDepartmentData(Checklist $checklist): void
    {
        $sortOrder = 1;

        // Computer/Laptops
        $group = $this->createGroup($checklist, self::CATEGORY_COMPUTERS, 'Hochwertige Arbeitsplatzrechner und mobile Geräte', $sortOrder++);
        $this->createCheckboxItem($group, 'Workstation-Klasse', [
            'High-End Workstation (32GB RAM, RTX 4080)',
            'Development Workstation (16GB RAM, GTX 1660)',
            'Standard Workstation (8GB RAM, Onboard-Grafik)'
        ], 1);
        $this->createRadioItem($group, 'Laptop-Typ', [
            'MacBook Pro 16" (M3 Pro)',
            'ThinkPad X1 Carbon',
            'Dell XPS 15',
            'Framework Laptop'
        ], 2);
        $this->createTextItem($group, 'Spezielle Hardware-Anforderungen', 3);

        // Monitore
        $group = $this->createGroup($checklist, self::CATEGORY_MONITORS, 'Displays für optimale Entwicklungsarbeit', $sortOrder++);
        $this->createCheckboxItem($group, 'Monitor-Setup', [
            '4K Monitor 32" (Hauptbildschirm)',
            '4K Monitor 27" (Zweitbildschirm)',
            'Ultrawide Monitor 34"',
            'Monitor-Arm (2-fach)',
            'Monitor-Arm (3-fach)'
        ], 1);
        $this->createRadioItem($group, 'Farbgenauigkeit', [
            'Standard (sRGB)',
            'Erweitert (Adobe RGB)',
            'Professionell (DCI-P3 + Kalibrierung)'
        ], 2);

        // Peripherie
        $group = $this->createGroup($checklist, self::CATEGORY_PERIPHERIE, 'Eingabegeräte und Zubehör', $sortOrder++);
        $this->createCheckboxItem($group, 'Eingabegeräte', [
            'Mechanische Tastatur (Cherry MX)',
            'Ergonomische Maus',
            'Trackball',
            'Graphics Tablet',
            'Webcam 4K',
            'Studio-Mikrofon',
            'Noise-Cancelling Kopfhörer'
        ], 1);
        $this->createRadioItem($group, 'Docking-Station', [
            'USB-C Dock (Standard)',
            'Thunderbolt 4 Dock',
            'KVM-Switch',
            self::OPTION_NOT_NEEDED
        ], 2);

        // Software/Lizenzen
        $group = $this->createGroup($checklist, self::CATEGORY_SOFTWARE, 'Entwicklungstools und Lizenzen', $sortOrder++);
        $this->createCheckboxItem($group, 'IDEs & Tools', [
            'JetBrains Ultimate (alle IDEs)',
            'Visual Studio Professional',
            'Sublime Text',
            'Adobe Creative Suite',
            'Figma Professional',
            'Docker Desktop Pro'
        ], 1);
        $this->createCheckboxItem($group, 'Cloud-Services', [
            'GitHub Copilot Business',
            'AWS Credits ($500/Monat)',
            'Google Cloud Credits',
            'Azure Credits'
        ], 2);
        $this->createTextItem($group, 'Weitere Software-Anforderungen', 3);

        // Mobilgeräte
        $group = $this->createGroup($checklist, self::CATEGORY_MOBILE, 'Smartphones und Tablets für Tests', $sortOrder++);
        $this->createRadioItem($group, 'Smartphone', [
            'iPhone 15 Pro',
            'iPhone 15',
            'Samsung Galaxy S24',
            'Google Pixel 8',
            self::OPTION_NOT_NEEDED
        ], 1);
        $this->createRadioItem($group, 'Tablet', [
            'iPad Pro 12.9"',
            'iPad Air',
            'Samsung Galaxy Tab S9',
            'Surface Pro 9',
            self::OPTION_NOT_NEEDED
        ], 2);

        // Büroausstattung
        $group = $this->createGroup($checklist, self::CATEGORY_OFFICE, 'Ergonomische Arbeitsplatzgestaltung', $sortOrder++);
        $this->createCheckboxItem($group, 'Möbel', [
            'Höhenverstellbarer Schreibtisch',
            'Ergonomischer Bürostuhl (Premium)',
            'Monitor-Schwenkarm',
            'Laptop-Ständer',
            'Kabelmanagement-System'
        ], 1);
        $this->createCheckboxItem($group, 'Beleuchtung', [
            'LED-Schreibtischlampe',
            'Hintergrundbeleuchtung (Bias Light)',
            'Ring Light für Video-Calls'
        ], 2);
    }

    private function createMarketingDepartmentData(Checklist $checklist): void
    {
        $sortOrder = 1;

        // Computer/Laptops
        $group = $this->createGroup($checklist, self::CATEGORY_COMPUTERS, 'Design-optimierte Arbeitsplätze', $sortOrder++);
        $this->createRadioItem($group, 'Arbeitsplatz-Typ', [
            'MacBook Pro 16" + 4K Monitor',
            'iMac 27" Retina',
            'Windows Workstation + 4K Monitor',
            'All-in-One PC'
        ], 1);
        $this->createCheckboxItem($group, 'Zusätzliche Hardware', [
            'Externe SSD 2TB',
            'Grafik-Tablet (Wacom)',
            'Colour-Checker für Farbkalibrierung'
        ], 2);

        // Monitore
        $group = $this->createGroup($checklist, self::CATEGORY_MONITORS, 'Farbgenaue Displays für Design', $sortOrder++);
        $this->createRadioItem($group, 'Hauptmonitor', [
            '4K Monitor 32" (100% sRGB)',
            '5K Monitor 27" (P3 Farbraum)',
            'OLED Monitor 27"'
        ], 1);
        $this->createCheckboxItem($group, 'Monitor-Zubehör', [
            'Monitor-Kalibrierungsgerät',
            'Monitor-Haube gegen Lichteinfall',
            'Schwenkarm für Monitor'
        ], 2);

        // Peripherie
        $group = $this->createGroup($checklist, self::CATEGORY_PERIPHERIE, 'Design-Tools und Eingabegeräte', $sortOrder++);
        $this->createCheckboxItem($group, 'Design-Hardware', [
            'Wacom Cintiq 22',
            'Wacom Intuos Pro',
            'Apple Magic Mouse',
            'Apple Magic Trackpad',
            'Präzisions-Maus für Design'
        ], 1);
        $this->createCheckboxItem($group, 'Audio/Video', [
            'Studio-Mikrofon',
            'Webcam 4K',
            'Softbox für Produktfotos',
            'Ringlicht'
        ], 2);

        // Software/Lizenzen
        $group = $this->createGroup($checklist, self::CATEGORY_SOFTWARE, 'Creative Software und Tools', $sortOrder++);
        $this->createCheckboxItem($group, 'Adobe Creative Cloud', [
            'Photoshop',
            'Illustrator',
            'InDesign',
            'After Effects',
            'Premiere Pro',
            'XD',
            'Lightroom'
        ], 1);
        $this->createCheckboxItem($group, 'Weitere Tools', [
            'Figma Professional',
            'Sketch',
            'Canva Pro',
            'Stock-Foto Lizenz'
        ], 2);

        // Mobilgeräte
        $group = $this->createGroup($checklist, self::CATEGORY_MOBILE, 'Geräte für Content-Erstellung', $sortOrder++);
        $this->createRadioItem($group, 'Smartphone', [
            'iPhone 15 Pro (Kamera-Features)',
            'Samsung Galaxy S24 Ultra',
            self::OPTION_NOT_NEEDED
        ], 1);
        $this->createRadioItem($group, 'Tablet', [
            'iPad Pro 12.9" + Apple Pencil',
            'Surface Pro 9 + Surface Pen',
            self::OPTION_NOT_NEEDED
        ], 2);

        // Büroausstattung
        $group = $this->createGroup($checklist, self::CATEGORY_OFFICE, 'Kreative Arbeitsumgebung', $sortOrder++);
        $this->createCheckboxItem($group, 'Möbel & Setup', [
            'Höhenverstellbarer Schreibtisch',
            'Design-Bürostuhl',
            'Inspiration Board/Pinnwand',
            'Pflanzen für kreative Atmosphäre'
        ], 1);
        $this->createTextItem($group, 'Spezielle Wünsche für den Arbeitsplatz', 2);
    }

    private function createOtherDepartmentData(Checklist $checklist): void
    {
        $sortOrder = 1;

        // Computer/Laptops
        $group = $this->createGroup($checklist, self::CATEGORY_COMPUTERS, 'Standard Büro-Computer', $sortOrder++);
        $this->createRadioItem($group, 'Computer-Typ', [
            'Desktop PC (Standard Office)',
            'Laptop 15" (Business-Klasse)',
            'Laptop 14" (Kompakt)',
            'All-in-One PC'
        ], 1);
        $this->createCheckboxItem($group, 'Zusatzausstattung', [
            'Externe Festplatte 1TB',
            'USB-Hub',
            'Laptop-Tasche'
        ], 2);

        // Monitore
        $group = $this->createGroup($checklist, self::CATEGORY_MONITORS, 'Standard Büro-Displays', $sortOrder++);
        $this->createRadioItem($group, 'Monitor-Größe', [
            '24" Full HD',
            '27" Full HD',
            '27" 4K (bei Bedarf)'
        ], 1);
        $this->createCheckboxItem($group, 'Monitor-Zubehör', [
            'Monitor-Ständer höhenverstellbar',
            'HDMI-Kabel',
            'VGA-Adapter'
        ], 2);

        // Peripherie
        $group = $this->createGroup($checklist, self::CATEGORY_PERIPHERIE, 'Standard Eingabegeräte', $sortOrder++);
        $this->createCheckboxItem($group, 'Eingabegeräte', [
            'Tastatur (Standard)',
            'Maus (optisch)',
            'Mousepad',
            'Webcam HD',
            'Headset für Video-Calls'
        ], 1);
        $this->createRadioItem($group, 'Anschlüsse', [
            'USB-Hub 4-Port',
            'Docking-Station (Basic)',
            self::OPTION_NOT_NEEDED
        ], 2);

        // Software/Lizenzen
        $group = $this->createGroup($checklist, self::CATEGORY_SOFTWARE, 'Standard Office-Software', $sortOrder++);
        $this->createCheckboxItem($group, 'Microsoft Office', [
            'Word',
            'Excel',
            'PowerPoint',
            'Outlook',
            'Teams',
            'OneNote'
        ], 1);
        $this->createCheckboxItem($group, 'Weitere Software', [
            'PDF-Reader (Adobe)',
            'Browser (Chrome/Firefox)',
            'Antivirus-Software'
        ], 2);
        $this->createTextItem($group, 'Spezielle Software-Anforderungen', 3);

        // Mobilgeräte
        $group = $this->createGroup($checklist, self::CATEGORY_MOBILE, 'Bei Bedarf mobile Geräte', $sortOrder++);
        $this->createRadioItem($group, 'Smartphone', [
            'Business-Smartphone (Standard)',
            self::OPTION_NOT_NEEDED
        ], 1);
        $this->createRadioItem($group, 'Tablet', [
            'Tablet 10" (für Präsentationen)',
            self::OPTION_NOT_NEEDED
        ], 2);

        // Büroausstattung
        $group = $this->createGroup($checklist, self::CATEGORY_OFFICE, 'Standard Arbeitsplatz', $sortOrder++);
        $this->createCheckboxItem($group, 'Möbel', [
            'Schreibtisch (Standard)',
            'Bürostuhl (ergonomisch)',
            'Rollcontainer',
            'Ablage-System'
        ], 1);
        $this->createCheckboxItem($group, 'Büromaterial', [
            'Schreibtischlampe',
            'Organizer',
            'Notizblöcke',
            'Stifte-Set'
        ], 2);
        $this->createTextItem($group, 'Besondere Wünsche', 3);
    }

    private function createGroup(Checklist $checklist, string $title, string $description, int $sortOrder): ChecklistGroup
    {
        $group = new ChecklistGroup();
        $group->setTitle($title);
        $group->setDescription($description);
        $group->setSortOrder($sortOrder);
        $group->setChecklist($checklist);

        $this->entityManager->persist($group);
        return $group;
    }

    private function createCheckboxItem(ChecklistGroup $group, string $label, array $options, int $sortOrder): GroupItem
    {
        $item = new GroupItem();
        $item->setLabel($label);
        $item->setType(GroupItem::TYPE_CHECKBOX);
        $item->setOptions(json_encode($options));
        $item->setSortOrder($sortOrder);
        $item->setGroup($group);

        $this->entityManager->persist($item);
        return $item;
    }

    private function createRadioItem(ChecklistGroup $group, string $label, array $options, int $sortOrder): GroupItem
    {
        $item = new GroupItem();
        $item->setLabel($label);
        $item->setType(GroupItem::TYPE_RADIO);
        $item->setOptions(json_encode($options));
        $item->setSortOrder($sortOrder);
        $item->setGroup($group);

        $this->entityManager->persist($item);
        return $item;
    }

    private function createTextItem(ChecklistGroup $group, string $label, int $sortOrder): GroupItem
    {
        $item = new GroupItem();
        $item->setLabel($label);
        $item->setType(GroupItem::TYPE_TEXT);
        $item->setOptions(null);
        $item->setSortOrder($sortOrder);
        $item->setGroup($group);

        $this->entityManager->persist($item);
        return $item;
    }

    private function getDefaultEmailTemplate(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Neue Ausstattungsbestellung</title>
</head>
<body>
    <h2>Neue Ausstattungsbestellung</h2>
    
    <p><strong>Mitarbeiter:</strong> {{name}}</p>
    <p><strong>Mitarbeiter-ID:</strong> {{mitarbeiter_id}}</p>
    <p><strong>Stückliste:</strong> {{stückliste}}</p>
    
    <h3>Bestellte Ausstattung:</h3>
    {{auswahl}}
    
    <hr>
    <p>Bei Rückfragen wenden Sie sich an: {{rueckfragen_email}}</p>
</body>
</html>
HTML;
    }

    private function getDefaultLinkEmailTemplate(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ausstattungsbestellung ausfüllen</title>
</head>
<body>
    <h2>Ausstattungsbestellung für neuen Mitarbeiter</h2>
    
    <p>Hallo {{recipient_name}},</p>
    
    <p>bitte füllen Sie die Ausstattungsliste für {{person_name}} aus:</p>
    
    <p><a href="{{link}}" style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Zur Ausstattungsliste</a></p>
    
    <p>{{intro}}</p>
    
    <p>Vielen Dank!</p>
</body>
</html>
HTML;
    }

    private function getDefaultConfirmationEmailTemplate(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Bestätigung Ihrer Ausstattungsbestellung</title>
</head>
<body>
    <h2>Bestätigung Ihrer Ausstattungsbestellung</h2>
    
    <p>Vielen Dank für das Ausfüllen der Ausstattungsliste!</p>
    
    <p><strong>Mitarbeiter:</strong> {{name}}</p>
    <p><strong>Mitarbeiter-ID:</strong> {{mitarbeiter_id}}</p>
    <p><strong>Stückliste:</strong> {{stückliste}}</p>
    
    <h3>Ihre Auswahl:</h3>
    {{auswahl}}
    
    <p>Die Bestellung wurde weitergeleitet und wird bearbeitet.</p>
    
    <hr>
    <p>Bei Rückfragen wenden Sie sich an: {{rueckfragen_email}}</p>
</body>
</html>
HTML;
    }
}
