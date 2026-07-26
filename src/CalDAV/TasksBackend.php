<?php

namespace App\CalDAV;

use App\Entity\Tache;
use App\Entity\User;
use App\Repository\TacheRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Sabre\VObject\Reader;

class TasksBackend
{
    public function __construct(
        private TacheRepository $tacheRepository,
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
    ) {
    }

    public function getTasksForUser(string $username): array
    {
        $user = $this->userRepository->findOneBy(['username' => $username]);
        if (!$user) {
            return [];
        }

        $tasks = $this->tacheRepository->findBy([
            'assignedUser' => $user,
        ]);

        return $tasks;
    }

    public function getTaskById(int $id): ?Tache
    {
        return $this->tacheRepository->find($id);
    }

    public function createTaskFromVTODO(string $vTODOData): ?Tache
    {
        $vObject = Reader::read($vTODOData);
        $vtodo = $vObject->VTODO;

        if (!$vtodo) {
            return null;
        }

        $task = new Tache();
        $task->setTitle((string) ($vtodo->SUMMARY ?? 'Sans titre'));
        $task->setContent((string) ($vtodo->DESCRIPTION ?? ''));

        if (isset($vtodo->DUE)) {
            $task->setDueDate($vtodo->DUE->getDateTime());
        }

        if (isset($vtodo->STATUS)) {
            $statusMap = [
                'NEEDS-ACTION' => Tache::STATUS_TODO,
                'IN-PROCESS' => Tache::STATUS_IN_PROGRESS,
                'COMPLETED' => Tache::STATUS_DONE,
            ];
            $status = (string) $vtodo->STATUS;
            $task->setStatus($statusMap[$status] ?? Tache::STATUS_TODO);
        }

        $this->em->persist($task);
        $this->em->flush();

        return $task;
    }

    public function updateTaskFromVTODO(Tache $task, string $vTODOData): void
    {
        $vObject = Reader::read($vTODOData);
        $vtodo = $vObject->VTODO;

        if (!$vtodo) {
            return;
        }

        $task->setTitle((string) ($vtodo->SUMMARY ?? $task->getTitle()));
        $task->setContent((string) ($vtodo->DESCRIPTION ?? $task->getContent()));

        if (isset($vtodo->DUE)) {
            $task->setDueDate($vtodo->DUE->getDateTime());
        }

        if (isset($vtodo->STATUS)) {
            $statusMap = [
                'NEEDS-ACTION' => Tache::STATUS_TODO,
                'IN-PROCESS' => Tache::STATUS_IN_PROGRESS,
                'COMPLETED' => Tache::STATUS_DONE,
            ];
            $status = (string) $vtodo->STATUS;
            $task->setStatus($statusMap[$status] ?? Tache::STATUS_TODO);
        }

        $task->setUpdatedAt(new \DateTime());
        $this->em->flush();
    }

    public function taskToVTODO(Tache $task): string
    {
        $vcal = "BEGIN:VCALENDAR\r\n";
        $vcal .= "VERSION:2.0\r\n";
        $vcal .= "PRODID:-//Ninegate//CalDAV//FR\r\n";
        $vcal .= "BEGIN:VTODO\r\n";
        $vcal .= "UID:tache-" . $task->getId() . "\r\n";
        $vcal .= "SUMMARY:" . $task->getTitle() . "\r\n";

        if ($task->getContent()) {
            $vcal .= "DESCRIPTION:" . str_replace("\n", "\\n", $task->getContent()) . "\r\n";
        }

        if ($task->getDueDate()) {
            $vcal .= "DUE:" . $task->getDueDate()->format('Ymd\THis') . "\r\n";
        }

        $statusMap = [
            Tache::STATUS_TODO => 'NEEDS-ACTION',
            Tache::STATUS_IN_PROGRESS => 'IN-PROCESS',
            Tache::STATUS_DONE => 'COMPLETED',
        ];
        $vcal .= "STATUS:" . ($statusMap[$task->getStatus()] ?? 'NEEDS-ACTION') . "\r\n";
        $vcal .= "DTSTAMP:" . (new \DateTime())->format('Ymd\THis') . "\r\n";
        $vcal .= "END:VTODO\r\n";
        $vcal .= "END:VCALENDAR\r\n";

        return $vcal;
    }
}
