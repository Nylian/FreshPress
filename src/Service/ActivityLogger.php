<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class ActivityLogger
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function log(
        ?User $user,
        string $action,
        ?string $description = null
    ): void {
        if ($user === null) {
            return;
        }

        $log = new ActivityLog();

        $log->setUserName(
            $user->getFullName() ?: $user->getUserIdentifier()
        );

        $log->setUserEmail(
            $user->getEmail() ?: $user->getUserIdentifier()
        );

        $log->setAction($action);
        $log->setDescription($description);
        $log->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }
}
