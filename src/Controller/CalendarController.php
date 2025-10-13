<?php

namespace App\Controller;

use App\Entity\Event;
use App\Form\EventFormType;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CalendarController extends AbstractController
{
    public function index(Request $request, EventRepository $events, EntityManagerInterface $em): Response
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
        $year = (int)($request->query->get('year') ?: (new \DateTimeImmutable())->format('Y'));
        $month = (int)($request->query->get('month') ?: (new \DateTimeImmutable())->format('n'));
        $month = max(1, min(12, $month));

        $monthStart = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month));
        $monthEnd = $monthStart->modify('last day of this month 23:59:59');

        $qb = $events->createQueryBuilder('e')
            ->andWhere('IDENTITY(e.family) = :fid')
            ->andWhere('(e.startAt BETWEEN :start AND :end) OR (e.recurrence = :yearly)')
            ->setParameter('fid', (int)$family->getId())
            ->setParameter('start', $monthStart)
            ->setParameter('end', $monthEnd)
            ->setParameter('yearly', Event::RECURRENCE_YEARLY)
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
            $date = $e->getStartAt();
            if ($e->getRecurrence() === Event::RECURRENCE_YEARLY) {
                $date = new \DateTimeImmutable(sprintf('%04d-%02d-%02d %s', $year, (int)$e->getStartAt()->format('m'), (int)$e->getStartAt()->format('d'), $e->getStartAt()->format('H:i:s')));
            }
            $key = $date->format('Y-m-d');
            $eventsByDay[$key] = $eventsByDay[$key] ?? [];
            $eventsByDay[$key][] = $e;
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

        return $this->render('calendar/index.html.twig', [
            'eventForm' => $form->createView(),
            'events' => $list,
            'month' => $monthStart,
            'weeks' => $weeks,
            'view' => $view,
            'year' => $year,
            'yearMonths' => $yearMonths,
            'prev' => $prev,
            'next' => $next,
            'families' => $families,
            'currentFamily' => $family,
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
}
