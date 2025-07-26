<?php

namespace App\Controller;

use App\Repository\EventRepository;
use App\Repository\PostRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DefaultController extends AbstractController
{
    public function __construct(
        private EventRepository $eventRepository,
        private PostRepository $postRepository
    ) {
    }

    #[Route('/', name: 'app_default')]
    public function index(): Response
    {
        $upcomingEvents = $this->eventRepository->findUpcoming(6);
        $recentPosts = $this->postRepository->findRecent(3);
        $eventStats = $this->eventRepository->getEventStats();

        return $this->render('default/index.html.twig', [
            'upcomingEvents' => $upcomingEvents,
            'recentPosts' => $recentPosts,
            'eventStats' => $eventStats,
        ]);
    }
}
