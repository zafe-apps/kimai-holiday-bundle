<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Repository\AbsenceRepository;

/** Open (requested) absences an approver is responsible for: menu badge and request page. */
class OpenRequestCounter
{
    public function __construct(
        private readonly ApproverResolver $resolver,
        private readonly AbsenceRepository $absenceRepository,
    ) {
    }

    public function countFor(User $approver): int
    {
        return \count($this->absenceRepository->findRequestedForUsers($this->resolver->subjectsFor($approver)));
    }
}
