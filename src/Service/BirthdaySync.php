<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\Family;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class BirthdaySync
{
    public function __construct(private EntityManagerInterface $em) {}

    public function syncForUserInFamily(User $user, Family $family): void
    {
        $birth = method_exists($user, 'getBirthDate') ? $user->getBirthDate() : null;
        if (!$birth) { return; }

        $repo = $this->em->getRepository(Event::class);
        $existing = $repo->createQueryBuilder('e')
            ->andWhere('e.family = :fam')
            ->andWhere('e.createdBy = :user')
            ->andWhere('e.recurrence = :rec')
            ->setParameter('fam', $family)
            ->setParameter('user', $user)
            ->setParameter('rec', Event::RECURRENCE_YEARLY)
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();

        $title = sprintf('Anniversaire de %s %s', $user->getFirstName(), strtoupper($user->getLastName()));
        $startAt = new \DateTimeImmutable($birth->format('Y-m-d') . ' 00:00:00');

        if ($existing) {
            $changed = false;
            if ($existing->getTitle() !== $title) { $existing->setTitle($title); $changed = true; }
            if ($existing->getStartAt()->format('Y-m-d') !== $startAt->format('Y-m-d')) { $existing->setStartAt($startAt); $changed = true; }
            if ($changed) { $this->em->flush(); }
            return;
        }

        $event = new Event();
        $event->setFamily($family)
            ->setCreatedBy($user)
            ->setTitle($title)
            ->setStartAt($startAt)
            ->setEndAt(null)
            ->setRecurrence(Event::RECURRENCE_YEARLY);
        $this->em->persist($event);
        $this->em->flush();
    }

    public function syncForUser(User $user): void
    {
        if (!method_exists($user, 'getFamilies')) { return; }
        foreach ($user->getFamilies() as $fam) {
            $this->syncForUserInFamily($user, $fam);
        }
    }
}

