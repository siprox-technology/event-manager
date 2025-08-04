<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\User;
use App\Form\EventType;
use App\Repository\EventRepository;
use App\Service\EventLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/events')]
class EventController extends AbstractController
{
    public function __construct(
        private EventRepository $eventRepository,
        private EntityManagerInterface $entityManager,
        private EventLogService $eventLogService,
        private CsrfTokenManagerInterface $csrfTokenManager
    ) {}

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

    #[Route('/new', name: 'app_event_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request): Response
    {
        $event = new Event();
        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $event->setCreatedBy($this->getUser());
            $event->setCreatedAt(new \DateTimeImmutable());
            $event->setUpdatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($event);
            $this->entityManager->flush();

            $this->eventLogService->logEventCreated($event);

            $this->addFlash('success', 'Event created successfully.');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        // Add flash message if form was submitted but has errors
        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Please check the form for errors.');

            // Debug: Log form errors for development
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', 'Error: ' . $error->getMessage());
            }

            // Debug: Check individual field errors
            foreach ($form->all() as $fieldName => $field) {
                if (!$field->isValid()) {
                    foreach ($field->getErrors() as $error) {
                        $this->addFlash('error', "Field '{$fieldName}': " . $error->getMessage());
                    }
                }
            }
        }

        return $this->render('event/new.html.twig', [
            'event' => $event,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_event_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Event $event): Response
    {
        return $this->render('event/show.html.twig', [
            'event' => $event,
            'canRegister' => $this->canUserRegister($event),
            'isRegistered' => $this->isUserRegistered($event),
            'canEdit' => $this->canUserEditEvent($event),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_event_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function edit(Request $request, Event $event): Response
    {
        if (!$this->canUserEditEvent($event)) {
            throw $this->createAccessDeniedException('You are not allowed to edit this event.');
        }

        // Create form with CSRF protection disabled for edit form temporarily
        $form = $this->createForm(EventType::class, $event, [
            'csrf_protection' => false
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $event->setUpdatedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
            $this->eventLogService->logEventUpdated($event, $this->getUser());
            $this->addFlash('success', 'Event updated successfully.');
            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        // Handle form submission with errors
        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Please check the form for errors.');

            // Log form errors for debugging
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', 'Error: ' . $error->getMessage());
            }

            // Check individual field errors
            foreach ($form->all() as $fieldName => $field) {
                if (!$field->isValid()) {
                    foreach ($field->getErrors() as $error) {
                        $this->addFlash('error', "Field '{$fieldName}': " . $error->getMessage());
                    }
                }
            }
        }

        return $this->render('event/edit.html.twig', [
            'event' => $event,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_event_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function delete(Request $request, Event $event): Response
    {
        if (!$this->canUserEditEvent($event)) {
            throw $this->createAccessDeniedException('You are not allowed to delete this event.');
        }

        if ($this->isCsrfTokenValid('delete' . $event->getId(), $request->request->get('_token'))) {
            // Log before deletion since we need the event data
            $this->eventLogService->log(
                'event_deleted',
                [
                    'event_id' => $event->getId(),
                    'event_title' => $event->getTitle(),
                    'event_status' => $event->getStatus(),
                ],
                $this->getUser()
            );

            $this->entityManager->remove($event);
            $this->entityManager->flush();

            $this->addFlash('success', 'Event deleted successfully.');
        }

        return $this->redirectToRoute('app_event_index');
    }

    #[Route('/{id}/register', name: 'app_event_register', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function register(Request $request, Event $event): Response
    {
        if (!$this->canUserRegister($event)) {
            $this->addFlash('error', 'You cannot register for this event.');
            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if ($this->isUserRegistered($event)) {
            $this->addFlash('warning', 'You are already registered for this event.');
            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if ($this->isCsrfTokenValid('register' . $event->getId(), $request->request->get('_token'))) {
            $event->addParticipant($this->getUser());
            $this->entityManager->flush();

            $this->eventLogService->logEventParticipantAdded($event, $this->getUser());

            $this->addFlash('success', 'You have successfully registered for this event.');
        }

        return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
    }

    #[Route('/{id}/unregister', name: 'app_event_unregister', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function unregister(Request $request, Event $event): Response
    {
        if (!$this->isUserRegistered($event)) {
            $this->addFlash('warning', 'You are not registered for this event.');
            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if ($this->isCsrfTokenValid('unregister' . $event->getId(), $request->request->get('_token'))) {
            $event->removeParticipant($this->getUser());
            $this->entityManager->flush();

            /** @var User $user */
            $user = $this->getUser();
            $this->eventLogService->log(
                'event_participant_removed',
                [
                    'event_id' => $event->getId(),
                    'event_title' => $event->getTitle(),
                    'participant_id' => $user->getId(),
                    'participant_email' => $user->getEmail(),
                ],
                $user
            );

            $this->addFlash('success', 'You have successfully unregistered from this event.');
        }

        return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
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
