<?php

namespace App\Controller;

use App\Service\VisitCounter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class HomeController extends AbstractController
{
    public function index(VisitCounter $counter): Response
    {
        $visits = $counter->incrementHome();
        return $this->render('home/index.html.twig', [
            'visits' => $visits,
        ]);
    }

    public function health(): Response
    {
        return new Response('OK', 200, ['Content-Type' => 'text/plain']);
    }
}
