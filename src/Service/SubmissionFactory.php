<?php

namespace App\Service;

use App\Entity\Checklist;
use App\Entity\Submission;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

class SubmissionFactory
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @param array<string, array<string, mixed>> $data
     */
    public function createSubmission(
        Checklist $checklist,
        string $name,
        string $mitarbeiterId,
        string $email,
        array $data,
        bool $persist = false
    ): Submission {
        $submission = new Submission();
        $submission->setChecklist($checklist);
        $submission->setName($name);
        $submission->setMitarbeiterId($mitarbeiterId);
        $submission->setEmail($email);
        $submission->setData($data);
        $submission->setSubmittedAt(new DateTimeImmutable());

        if ($persist) {
            $this->entityManager->persist($submission);
            $this->entityManager->flush();
        }

        return $submission;
    }
}
