<?php

namespace App\Controller;

use App\Entity\Event;
use App\Repository\EventRepository;
use App\Service\EventLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/events')]
class EventController extends AbstractController
{
    public function __construct(
        private EventRepository $eventRepository,
        private EntityManagerInterface $entityManager,
        private EventLogService $eventLogService
    ) {
    }

    #[Route('', name: 'app_event_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $status = $request->query->get('status');
        $location = $request->query->get('location');
        $search = $request->query->get('search');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $events = $this->eventRepository->findWithFilters(
            status: $status,
            startDate: null,
            endDate: null,
            location: $location,
            search: $search,
            orderBy: 'startDate',
            order: 'ASC',
            limit: $limit,
            offset: $offset
        );

        $totalEvents = $this->eventRepository->countWithFilters(
            status: $status,
            startDate: null,
            endDate: null,
            location: $location,
            search: $search
        );

        $totalPages = ceil($totalEvents / $limit);

        return $this->render('event/index.html.twig', [
            'events' => $events,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalEvents' => $totalEvents,
            'filters' => [
                'status' => $status,
                'location' => $location,
                'search' => $search,
            ],
            'availableStatuses' => Event::getAvailableStatuses(),
        ]);
    }

    #[Route('/upcoming', name: 'app_event_upcoming', methods: ['GET'])]
    public function upcoming(): JsonResponse
    {
        $events = $this->eventRepository->findUpcoming(10);
        
        $eventData = array_map(function (Event $event) {
            return [
                'id' => $event->getId(),
                'title' => $event->getTitle(),
                'description' => $event->getDescription(),
                'location' => $event->getLocation(),
                'startDate' => $event->getStartDate()?->format('Y-m-d H:i:s'),
                'endDate' => $event->getEndDate()?->format('Y-m-d H:i:s'),
                'status' => $event->getStatus(),
                'participantCount' => $event->getParticipantCount(),
                'maxParticipants' => $event->getMaxParticipants(),
                'canAcceptMoreParticipants' => $event->canAcceptMoreParticipants(),
            ];
        }, $events);

        return new JsonResponse($eventData);
    }

    #[Route('/{id}', name: 'app_event_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Event $event): Response
    {
        return $this->render('event/show.html.twig', [
            'event' => $event,
            'canRegister' => $this->canUserRegister($event),
            'isRegistered' => $this->isUserRegistered($event),
        ]);
    }

    private function canUserRegister(Event $event): bool
    {
        return $event->getStatus() === Event::STATUS_PLANNED 
            && $event->canAcceptMoreParticipants()
            && $event->isUpcoming();
    }

    private function isUserRegistered(Event $event): bool
    {
        $user = $this->getUser();
        return $user && $event->isParticipant($user);
    }

    private function canUserEditEvent(Event $event): bool
    {
        $user = $this->getUser();
        return $user && (
            $event->getCreatedBy() === $user || 
            in_array('ROLE_ADMIN', $user->getRoles())
        );
    }
}
