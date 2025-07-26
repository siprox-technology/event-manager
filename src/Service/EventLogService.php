<?php

namespace App\Service;

use App\Entity\EventLog;
use App\Entity\User;
use App\Repository\EventLogRepository;
use Symfony\Component\HttpFoundation\RequestStack;

class EventLogService
{
    public function __construct(
        private EventLogRepository $eventLogRepository,
        private RequestStack $requestStack
    ) {
    }

    public function log(
        string $eventType,
        array $payload = [],
        ?User $user = null
    ): EventLog {
        $request = $this->requestStack->getCurrentRequest();
        
        $ipAddress = null;
        $userAgent = null;
        
        if ($request) {
            $ipAddress = $request->getClientIp();
            $userAgent = $request->headers->get('User-Agent');
        }

        return $this->eventLogRepository->logEvent(
            $eventType,
            $payload,
            $user,
            $ipAddress,
            $userAgent
        );
    }

    public function logUserEvent(string $eventType, User $user, array $payload = []): EventLog
    {
        return $this->log($eventType, $payload, $user);
    }

    public function logEventCreated(\App\Entity\Event $event): EventLog
    {
        return $this->log(
            EventLog::TYPE_EVENT_CREATED,
            [
                'event_id' => $event->getId(),
                'event_title' => $event->getTitle(),
                'event_status' => $event->getStatus(),
                'start_date' => $event->getStartDate()?->format('Y-m-d H:i:s'),
                'end_date' => $event->getEndDate()?->format('Y-m-d H:i:s'),
            ],
            $event->getCreatedBy()
        );
    }

    public function logEventUpdated(\App\Entity\Event $event, User $updatedBy): EventLog
    {
        return $this->log(
            EventLog::TYPE_EVENT_UPDATED,
            [
                'event_id' => $event->getId(),
                'event_title' => $event->getTitle(),
                'event_status' => $event->getStatus(),
            ],
            $updatedBy
        );
    }

    public function logEventParticipantAdded(\App\Entity\Event $event, User $participant): EventLog
    {
        return $this->log(
            EventLog::TYPE_EVENT_PARTICIPANT_ADDED,
            [
                'event_id' => $event->getId(),
                'event_title' => $event->getTitle(),
                'participant_id' => $participant->getId(),
                'participant_email' => $participant->getEmail(),
            ],
            $participant
        );
    }

    public function logPostCreated(\App\Entity\Post $post): EventLog
    {
        return $this->log(
            EventLog::TYPE_POST_CREATED,
            [
                'post_id' => $post->getId(),
                'post_title' => $post->getTitle(),
                'post_slug' => $post->getSlug(),
                'is_published' => $post->isPublished(),
            ],
            $post->getAuthor()
        );
    }

    public function logPostPublished(\App\Entity\Post $post): EventLog
    {
        return $this->log(
            EventLog::TYPE_POST_PUBLISHED,
            [
                'post_id' => $post->getId(),
                'post_title' => $post->getTitle(),
                'post_slug' => $post->getSlug(),
            ],
            $post->getAuthor()
        );
    }

    public function logCommentCreated(\App\Entity\Comment $comment): EventLog
    {
        return $this->log(
            EventLog::TYPE_COMMENT_CREATED,
            [
                'comment_id' => $comment->getId(),
                'post_id' => $comment->getPost()?->getId(),
                'post_title' => $comment->getPost()?->getTitle(),
                'is_reply' => $comment->isReply(),
                'parent_comment_id' => $comment->getParent()?->getId(),
            ],
            $comment->getAuthor()
        );
    }

    public function logUserRegistered(User $user): EventLog
    {
        return $this->log(
            EventLog::TYPE_USER_REGISTERED,
            [
                'user_id' => $user->getId(),
                'user_email' => $user->getEmail(),
                'user_roles' => $user->getRoles(),
            ],
            $user
        );
    }

    public function logUserLogin(User $user): EventLog
    {
        return $this->log(
            EventLog::TYPE_USER_LOGIN,
            [
                'user_id' => $user->getId(),
                'user_email' => $user->getEmail(),
            ],
            $user
        );
    }

    public function logUserLogout(User $user): EventLog
    {
        return $this->log(
            EventLog::TYPE_USER_LOGOUT,
            [
                'user_id' => $user->getId(),
                'user_email' => $user->getEmail(),
            ],
            $user
        );
    }
}
