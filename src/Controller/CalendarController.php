<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\WorkPattern;
use App\Entity\WorkOverride;
use App\Form\EventFormType;
use App\Form\WorkPatternFormType;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CalendarController extends AbstractController
{
    public function index(Request $request, EventRepository $events, EntityManagerInterface $em, \App\Service\BirthdaySync $birthdaySync): Response
    {
        $user = $this->getUser();
        if (!$user) { return $this->redirectToRoute('app_login'); }

        $families = $user->getFamilies();
        $fid = (int)($request->query->get('fid') ?? 0);
        $family = null;
        if ($fid) {
            foreach ($families as $f) { if ($f->getId() === $fid) { $family = $f; break; } }
            if (!$family && $this->isGranted('ROLE_ADMIN')) {
                $family = $em->getRepository(\App\Entity\Family::class)->find($fid);
            }
        }
        if (!$family) {
            if (count($families) === 0) {
                // pas de famille: seulement autoriser admin si fid invalide -> page vide
                return $this->redirectToRoute('family_home');
            }
            $family = $families->first();
        }

        // S'assurer que l'anniversaire de l'utilisateur courant est synchronisé dans cette famille
        try { $birthdaySync->syncForUserInFamily($user, $family); } catch (\Throwable $e) {}

        $event = new Event();
        $form = $this->createForm(EventFormType::class, $event);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $event->setFamily($family);
            $event->setCreatedBy($user);
            $em->persist($event);
            $em->flush();
            $this->addFlash('success', 'Événement ajouté.');
            return $this->redirectToRoute('calendar_home', ['fid' => $family->getId()]);
        }

        $view = (string)($request->query->get('view', 'month'));
        $tab = (string)($request->query->get('tab', 'family'));
        $year = (int)($request->query->get('year') ?: (new \DateTimeImmutable())->format('Y'));
        $month = (int)($request->query->get('month') ?: (new \DateTimeImmutable())->format('n'));
        $month = max(1, min(12, $month));

        $monthStart = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month));
        $monthEnd = $monthStart->modify('last day of this month 23:59:59');

        $qb = $events->createQueryBuilder('e')
            ->andWhere('IDENTITY(e.family) = :fid')
            ->andWhere('(e.startAt BETWEEN :start AND :end) OR (e.recurrence IN (:recs))')
            ->setParameter('fid', (int)$family->getId())
            ->setParameter('start', $monthStart)
            ->setParameter('end', $monthEnd)
            ->setParameter('recs', [Event::RECURRENCE_YEARLY, Event::RECURRENCE_MONTHLY, Event::RECURRENCE_WEEKLY])
            ->orderBy('e.startAt', 'ASC');

        $list = $qb->getQuery()->getResult();

        // Build calendar grid weeks (Mon..Sun)
        $firstDow = (int)$monthStart->format('N');
        $gridStart = $monthStart->modify('-' . ($firstDow - 1) . ' days');
        $lastDow = (int)$monthEnd->format('N');
        $gridEnd = $monthEnd->modify('+' . (7 - $lastDow) . ' days');

        $day = $gridStart;
        $weeks = [];
        $week = [];

        // Index events by Y-m-d (including yearly on same month/day)
        $eventsByDay = [];
        foreach ($list as $e) {
            $rec = $e->getRecurrence();
            if ($rec === Event::RECURRENCE_YEARLY) {
                $date = new \DateTimeImmutable(sprintf('%04d-%02d-%02d %s', $year, (int)$e->getStartAt()->format('m'), (int)$e->getStartAt()->format('d'), $e->getStartAt()->format('H:i:s')));
                $key = $date->format('Y-m-d');
                $eventsByDay[$key] = $eventsByDay[$key] ?? [];
                $eventsByDay[$key][] = $e;
            } elseif ($rec === Event::RECURRENCE_MONTHLY) {
                $dom = (int)$e->getStartAt()->format('d');
                $lastDay = (int)$monthEnd->format('d');
                $targetDay = min($dom, $lastDay);
                $date = new \DateTimeImmutable(sprintf('%04d-%02d-%02d %s', $year, (int)$monthStart->format('m'), $targetDay, $e->getStartAt()->format('H:i:s')));
                $key = $date->format('Y-m-d');
                $eventsByDay[$key] = $eventsByDay[$key] ?? [];
                $eventsByDay[$key][] = $e;
            } elseif ($rec === Event::RECURRENCE_WEEKLY) {
                $targetDow = (int)$e->getStartAt()->format('N');
                $cur = $gridStart;
                while ($cur <= $gridEnd) {
                    if ((int)$cur->format('N') === $targetDow) {
                        $key = $cur->format('Y-m-d');
                        $eventsByDay[$key] = $eventsByDay[$key] ?? [];
                        $eventsByDay[$key][] = $e;
                    }
                    $cur = $cur->modify('+1 day');
                }
            } else {
                $date = $e->getStartAt();
                $key = $date->format('Y-m-d');
                $eventsByDay[$key] = $eventsByDay[$key] ?? [];
                $eventsByDay[$key][] = $e;
            }
        }

        while ($day <= $gridEnd) {
            $key = $day->format('Y-m-d');
            $week[] = [
                'date' => $day,
                'inMonth' => (int)$day->format('n') === $month,
                'events' => $eventsByDay[$key] ?? [],
            ];
            if (count($week) === 7) { $weeks[] = $week; $week = []; }
            $day = $day->modify('+1 day');
        }

        // Year view data: events grouped per month
        $yearMonths = [];
        if ($view === 'year') {
            for ($m = 1; $m <= 12; $m++) {
                $ms = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $m));
                $me = $ms->modify('last day of this month 23:59:59');
                $qby = $events->createQueryBuilder('e2')
                    ->andWhere('IDENTITY(e2.family) = :fid')
                    ->andWhere('(e2.startAt BETWEEN :start AND :end) OR (e2.recurrence = :yearly)')
                    ->setParameter('fid', (int)$family->getId())
                    ->setParameter('start', $ms)
                    ->setParameter('end', $me)
                    ->setParameter('yearly', Event::RECURRENCE_YEARLY)
                    ->orderBy('e2.startAt', 'ASC');
                $items = $qby->getQuery()->getResult();
                // filter yearly to month match
                $filtered = array_values(array_filter($items, function(Event $e) use ($m) {
                    if ($e->getRecurrence() !== Event::RECURRENCE_YEARLY) return true;
                    return (int)$e->getStartAt()->format('n') === $m;
                }));
                $yearMonths[$m] = $filtered;
            }
        }

        // Prev/next calculations
        $prev = $monthStart->modify('-1 month');
        $next = $monthStart->modify('+1 month');
        $prevYearInt = $year - 1;
        $nextYearInt = $year + 1;

        // Work schedules (for work tab)
        $workPatterns = [];
        $workByDay = [];
        $workForm = null;
        $canEditWork = true; // any member can define their own pattern
        if ($tab === 'work') {
            $workPatterns = $em->getRepository(WorkPattern::class)->findBy(['family' => $family]);
            // collect overrides for grid range
            $patternIds = array_map(fn(WorkPattern $p) => $p->getId(), $workPatterns);
            $overrides = [];
            if (count($patternIds) > 0) {
                $ovrList = $em->getRepository(WorkOverride::class)
                    ->createQueryBuilder('o')
                    ->where('IDENTITY(o.pattern) IN (:p)')
                    ->andWhere('o.date BETWEEN :start AND :end')
                    ->setParameter('p', $patternIds)
                    ->setParameter('start', $gridStart)
                    ->setParameter('end', $gridEnd)
                    ->getQuery()->getResult();
                foreach ($ovrList as $o) {
                    $overrides[$o->getPattern()->getId().'@'.$o->getDate()->format('Y-m-d')] = $o->getValue();
                }
            }
            // compute shifts per day per pattern
            $cur = $gridStart;
            while ($cur <= $gridEnd) {
                $key = $cur->format('Y-m-d');
                foreach ($workPatterns as $p) {
                    $pat = $p->getPattern();
                    $len = max(1, (int)$p->getCycleLength());
                    if ($len < 1) { $len = count($pat) ?: 1; }
                    $anchor = (new \DateTimeImmutable($p->getStartDate()->format('Y-m-d').' 00:00:00'));
                    $delta = intdiv($cur->getTimestamp() - $anchor->getTimestamp(), 86400);
                    $idx = (($delta % $len) + $len) % $len;
                    $val = isset($pat[$idx]) ? (int)$pat[$idx] : WorkPattern::SHIFT_REST;
                    $ovrKey = $p->getId().'@'.$key;
                    if (isset($overrides[$ovrKey])) { $val = (int)$overrides[$ovrKey]; }
                    $workByDay[$key] = $workByDay[$key] ?? [];
                    $workByDay[$key][] = [
                        'pattern' => $p,
                        'value' => $val,
                    ];
                }
                $cur = $cur->modify('+1 day');
            }
            // Work pattern form (create)
            $wp = new WorkPattern();
            $workForm = $this->createForm(WorkPatternFormType::class, $wp);
            $workForm->handleRequest($request);
            if ($workForm->isSubmitted() && $workForm->isValid()) {
                $wp->setFamily($family);
                $wp->setOwner($user);
                $len = $wp->getCycleLength();
                $len = max(7, min(21, $len));
                $out = [];
                for ($i=1; $i <= $len; $i++) {
                    $out[] = (int)$workForm->get('d'.$i)->getData();
                }
                $wp->setPattern($out);
                $em->persist($wp);
                $em->flush();
                $this->addFlash('success', 'Rythme de travail enregistré.');
                return $this->redirectToRoute('calendar_home', ['fid' => $family->getId(), 'tab' => 'work']);
            }
        }

        return $this->render('calendar/index.html.twig', [
            'eventForm' => $form->createView(),
            'events' => $list,
            'month' => $monthStart,
            'weeks' => $weeks,
            'view' => $view,
            'tab' => $tab,
            'year' => $year,
            'yearMonths' => $yearMonths,
            'prev' => $prev,
            'next' => $next,
            'prevYear' => $prevYearInt,
            'nextYear' => $nextYearInt,
            'families' => $families,
            'currentFamily' => $family,
            'workPatterns' => $workPatterns,
            'workByDay' => $workByDay,
            'workForm' => $workForm?->createView(),
        ]);
    }

    #[Route('/calendar/event/{id}/delete', name: 'calendar_event_delete', methods: ['POST'])]
    public function deleteEvent(int $id, Request $request, EventRepository $events, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) { return $this->redirectToRoute('app_login'); }
        $event = $events->find($id);
        if (!$event) { $this->addFlash('error', 'Événement introuvable.'); return $this->redirectToRoute('calendar_home'); }
        $family = $event->getFamily();
        if (!$family) { $this->addFlash('error', 'Famille introuvable pour cet événement.'); return $this->redirectToRoute('calendar_home'); }
        $isMember = false; foreach ($user->getFamilies() as $f) { if ($f->getId() === $family->getId()) { $isMember = true; break; } }
        if (!$isMember && !$this->isGranted('ROLE_ADMIN')) { $this->addFlash('error', 'Accès refusé.'); return $this->redirectToRoute('calendar_home'); }
        $canDelete = ($event->getCreatedBy()?->getId() === $user->getId()) || ($family->getOwner()?->getId() === $user->getId()) || $this->isGranted('ROLE_ADMIN');
        if (!$canDelete) { $this->addFlash('error', 'Vous ne pouvez pas supprimer cet événement.'); return $this->redirectToRoute('calendar_home'); }
        if (!$this->isCsrfTokenValid('delete_event_'.$event->getId(), (string)$request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('calendar_home');
        }
        $em->remove($event);
        $em->flush();
        $this->addFlash('success', 'Événement supprimé.');
        return $this->redirectToRoute('calendar_home', ['fid' => $family->getId()]);
    }

    #[Route('/calendar/work/pattern/{id}/delete', name: 'work_pattern_delete', methods: ['POST'])]
    public function deleteWorkPattern(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) { return $this->redirectToRoute('app_login'); }
        $pattern = $em->getRepository(WorkPattern::class)->find($id);
        if (!$pattern) { $this->addFlash('error', 'Rythme introuvable.'); return $this->redirectToRoute('calendar_home', ['tab' => 'work']); }
        $family = $pattern->getFamily();
        $isMember = false; foreach ($user->getFamilies() as $f) { if ($family && $f->getId() === $family->getId()) { $isMember = true; break; } }
        if (!$isMember && !$this->isGranted('ROLE_ADMIN')) { $this->addFlash('error', 'Accès refusé.'); return $this->redirectToRoute('calendar_home', ['tab' => 'work']); }
        $canDelete = ($pattern->getOwner()?->getId() === $user->getId()) || ($family && $family->getOwner() && $family->getOwner()->getId() === $user->getId()) || $this->isGranted('ROLE_ADMIN');
        if (!$canDelete) { $this->addFlash('error', 'Vous ne pouvez pas supprimer ce rythme.'); return $this->redirectToRoute('calendar_home', ['tab' => 'work']); }
        if (!$this->isCsrfTokenValid('work_delete_'.$pattern->getId(), (string)$request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('calendar_home', ['tab' => 'work']);
        }
        $fid = $family?->getId();
        $em->remove($pattern);
        $em->flush();
        $this->addFlash('success', 'Rythme supprimé.');
        return $this->redirectToRoute('calendar_home', ['tab' => 'work', 'fid' => $fid]);
    }

    #[Route('/calendar/work/update', name: 'work_update', methods: ['POST'])]
    public function updateWork(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) { return $this->redirectToRoute('app_login'); }
        $pid = (int)$request->request->get('pattern_id');
        $dateStr = (string)$request->request->get('date');
        $value = (int)$request->request->get('value');
        $token = (string)$request->request->get('_token');
        if (!$this->isCsrfTokenValid('work_update_'.$pid.'_'.$dateStr, $token)) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('calendar_home', ['tab' => 'work']);
        }
        $pattern = $em->getRepository(WorkPattern::class)->find($pid);
        if (!$pattern) { $this->addFlash('error', 'Rythme introuvable.'); return $this->redirectToRoute('calendar_home', ['tab' => 'work']); }
        $family = $pattern->getFamily();
        $isMember = false; foreach ($user->getFamilies() as $f) { if ($family && $f->getId() === $family->getId()) { $isMember = true; break; } }
        if (!$isMember && !$this->isGranted('ROLE_ADMIN')) { $this->addFlash('error', 'Accès refusé.'); return $this->redirectToRoute('calendar_home', ['tab' => 'work']); }
        $canEdit = ($pattern->getOwner()?->getId() === $user->getId()) || ($family && $family->getOwner() && $family->getOwner()->getId() === $user->getId()) || $this->isGranted('ROLE_ADMIN');
        if (!$canEdit) { $this->addFlash('error', 'Vous ne pouvez pas modifier ce rythme.'); return $this->redirectToRoute('calendar_home', ['tab' => 'work']); }
        try { $date = new \DateTimeImmutable($dateStr.' 00:00:00'); } catch (\Throwable) { $this->addFlash('error', 'Date invalide.'); return $this->redirectToRoute('calendar_home', ['tab' => 'work']); }
        $value = max(0, min(WorkPattern::SHIFT_TRAVEL, $value));
        // Upsert override
        $ovr = $em->getRepository(WorkOverride::class)->findOneBy(['pattern' => $pattern, 'date' => $date]);
        if (!$ovr) { $ovr = new WorkOverride(); $ovr->setPattern($pattern)->setDate($date); $em->persist($ovr); }
        $ovr->setValue($value);
        $em->flush();
        $this->addFlash('success', 'Jour mis à jour.');
        return $this->redirectToRoute('calendar_home', ['tab' => 'work', 'fid' => $family?->getId()]);
    }
}
