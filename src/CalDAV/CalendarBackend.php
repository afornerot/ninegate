<?php

namespace App\CalDAV;

use App\Entity\Calendar;
use App\Entity\CalendarEvent;
use App\Entity\User;
use App\Repository\CalendarEventRepository;
use App\Repository\CalendarRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Sabre\CalDAV\Backend\AbstractBackend;
use Sabre\CalDAV\CalendarObject;
use Sabre\DAV\PropPatch;

class CalendarBackend extends AbstractBackend
{
    public function __construct(
        private CalendarRepository $calendarRepository,
        private CalendarEventRepository $calendarEventRepository,
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
    ) {
    }

    private function getUsernameFromPrincipal(string $principal): string
    {
        $parts = explode('/', $principal);
        return end($parts);
    }

    private function getUserFromPrincipal(string $principal): ?User
    {
        $username = $this->getUsernameFromPrincipal($principal);
        return $this->userRepository->findOneBy(['username' => $username]);
    }

    private function toDateTime($dt): ?\DateTime
    {
        if (!$dt) {
            return null;
        }
        if ($dt instanceof \DateTimeImmutable) {
            return \DateTime::createFromImmutable($dt);
        }
        if ($dt instanceof \DateTime) {
            return $dt;
        }
        return null;
    }

    public function getCalendarsForUser($principalUri): array
    {
        $user = $this->getUserFromPrincipal($principalUri);
        if (!$user) {
            return [];
        }

        $calendars = $this->calendarRepository->findAccessibleCalendars($user);
        $result = [];

        foreach ($calendars as $calendar) {
            $result[] = $this->mapCalendarToSabre($calendar);
        }

        return $result;
    }

    public function createCalendar($principalUri, $calendarUri, array $properties): void
    {
        $user = $this->getUserFromPrincipal($principalUri);
        if (!$user) {
            return;
        }

        $calendar = new Calendar();
        $calendar->setTitle($properties['{DAV:}displayname'] ?? $calendarUri);
        $calendar->setSlug($calendarUri);
        $calendar->setColor($properties['{http://apple.com/ns/ical/}calendar-color'] ?? null);
        $calendar->setUser($user);
        $calendar->setCalendarOrder(0);

        $this->em->persist($calendar);
        $this->em->flush();
    }

    public function updateCalendar($calendarId, PropPatch $propPatch): void
    {
        $calendar = $this->calendarRepository->find((int) $calendarId);
        if (!$calendar) {
            return;
        }

        $propPatch->handle('{DAV:}displayname', function ($value) use ($calendar) {
            $calendar->setTitle($value);
            return true;
        });

        $propPatch->handle('{http://apple.com/ns/ical/}calendar-color', function ($value) use ($calendar) {
            $calendar->setColor($value);
            return true;
        });

        $this->em->flush();
    }

    public function deleteCalendar($calendarId): void
    {
        $calendar = $this->calendarRepository->find((int) $calendarId);
        if ($calendar) {
            $this->em->remove($calendar);
            $this->em->flush();
        }
    }

    public function getCalendarObjects($calendarId): array
    {
        $calendar = $this->calendarRepository->find((int) $calendarId);
        if (!$calendar) {
            return [];
        }

        $events = $this->calendarEventRepository->findBy(['calendar' => $calendar]);
        $result = [];

        foreach ($events as $event) {
            $result[] = $this->mapEventToSabre($event);
        }

        return $result;
    }

    public function getCalendarObject($calendarId, $objectUri): ?array
    {
        $event = $this->findEventByUri($calendarId, $objectUri);
        if (!$event) {
            return null;
        }

        $calendarData = $this->eventToICalendar($event);
        $etag = md5($calendarData);

        return [
            'uri' => $objectUri,
            'calendardata' => $calendarData,
            'etag' => '"' . $etag . '"',
            'size' => strlen($calendarData),
            'lastmodified' => ($event->getUpdatedAt() ?: $event->getCreatedAt()) ? ($event->getUpdatedAt() ?: $event->getCreatedAt())->getTimestamp() : time(),
        ];
    }

    public function getMultipleCalendarObjects($calendarId, array $uris): array
    {
        $result = [];
        foreach ($uris as $uri) {
            $obj = $this->getCalendarObject($calendarId, $uri);
            if ($obj) {
                $result[] = $obj;
            }
        }
        return $result;
    }

    public function createCalendarObject($calendarId, $objectUri, $calendarData): void
    {
        $calendar = $this->calendarRepository->find((int) $calendarId);
        if (!$calendar) {
            return;
        }

        $vObject = \Sabre\VObject\Reader::read($calendarData);
        $vevent = $vObject->VEVENT;

        if (!$vevent) {
            return;
        }

        $event = new CalendarEvent();
        $event->setTitle((string) $vevent->SUMMARY);
        $event->setDescription((string) ($vevent->DESCRIPTION ?? ''));
        $event->setSlug(md5($objectUri));
        $event->setCalendar($calendar);
        $event->setUser($calendar->getUser() ?? $this->getDefaultUser());

        if (isset($vevent->DTSTART)) {
            $event->setStartDate($this->toDateTime($vevent->DTSTART->getDateTime()));
        }
        if (isset($vevent->DTEND)) {
            $event->setEndDate($this->toDateTime($vevent->DTEND->getDateTime()));
        }
        if (isset($vevent->CATEGORIES)) {
            $event->setColor((string) $vevent->CATEGORIES);
        }

        $this->em->persist($event);
        $this->em->flush();
        return;
    }

    public function updateCalendarObject($calendarId, $objectUri, $calendarData): void
    {
        $calendar = $this->calendarRepository->find((int) $calendarId);
        if (!$calendar) {
            return;
        }

        $vObject = \Sabre\VObject\Reader::read($calendarData);
        $vevent = $vObject->VEVENT;

        if (!$vevent) {
            return;
        }

        $event = $this->findEventByUri($calendarId, $objectUri);
        if (!$event) {
            return;
        }

        $event->setTitle((string) $vevent->SUMMARY);
        $event->setDescription((string) ($vevent->DESCRIPTION ?? ''));

        if (isset($vevent->DTSTART)) {
            $event->setStartDate($this->toDateTime($vevent->DTSTART->getDateTime()));
        }
        if (isset($vevent->DTEND)) {
            $event->setEndDate($this->toDateTime($vevent->DTEND->getDateTime()));
        }
        if (isset($vevent->CATEGORIES)) {
            $event->setColor((string) $vevent->CATEGORIES);
        }

        $event->setUpdatedAt(new \DateTime());
        $this->em->flush();
    }

    public function deleteCalendarObject($calendarId, $objectUri): void
    {
        $event = $this->findEventByUri($calendarId, $objectUri);
        if ($event) {
            $this->em->remove($event);
            $this->em->flush();
        }
    }

    public function calendarQuery($calendarId, array $filters): array
    {
        return [];
    }

    public function getCalendarObjectByUID($principalUri, $uid): ?CalendarObject
    {
        return null;
    }

    private function findEventByUri($calendarId, $objectUri): ?CalendarEvent
    {
        $slug = $objectUri;
        if (str_contains($objectUri, '/')) {
            $slug = basename($objectUri, '.ics');
        } else {
            $slug = str_replace('.ics', '', $objectUri);
        }

        $event = $this->calendarEventRepository->findOneBy(['slug' => $slug]);
        if (!$event) {
            $events = $this->calendarEventRepository->findBy(['calendar' => (int) $calendarId]);
            foreach ($events as $e) {
                if ($e->getSlug() . '.ics' === $objectUri || $slug . '.ics' === $objectUri) {
                    return $e;
                }
            }
        }

        return $event;
    }

    private function mapCalendarToSabre(Calendar $calendar): array
    {
        $id = (string) $calendar->getId();
        $principalUri = 'principals/' . ($calendar->getUser() ? $calendar->getUser()->getUsername() : 'admin');

        return [
            'id' => $id,
            'uri' => $id,
            'principaluri' => $principalUri,
            '{DAV:}displayname' => $calendar->getTitle(),
            '{http://calendarserver.org/ns/}getctag' => 'ctag-' . $id,
            '{http://apple.com/ns/ical/}calendar-color' => $calendar->getColor() ?? '#007aff',
            '{http://sabre.io/ns/sync/}sync-token' => '1',
            '{DAV:}getlastmodified' => $calendar->getCreatedAt() ? $calendar->getCreatedAt()->getTimestamp() : time(),
        ];
    }

    private function mapEventToSabre(CalendarEvent $event): array
    {
        $slug = $event->getSlug() . '.ics';

        return [
            'id' => $slug,
            'uri' => $slug,
            '{DAV:}getetag' => '"' . md5($slug) . '"',
            '{DAV:}getcontenttype' => 'text/calendar',
            'lastmodified' => ($event->getUpdatedAt() ?: $event->getCreatedAt()) ? ($event->getUpdatedAt() ?: $event->getCreatedAt())->getTimestamp() : time(),
        ];
    }

    private function eventToICalendar(CalendarEvent $event): string
    {
        $vcal = "BEGIN:VCALENDAR\r\n";
        $vcal .= "VERSION:2.0\r\n";
        $vcal .= "PRODID:-//Ninegate//CalDAV//FR\r\n";
        $vcal .= "CALSCALE:GREGORIAN\r\n";
        $vcal .= "BEGIN:VEVENT\r\n";
        $vcal .= "UID:" . $event->getSlug() . "\r\n";
        $vcal .= "SUMMARY:" . $event->getTitle() . "\r\n";

        if ($event->getDescription()) {
            $vcal .= "DESCRIPTION:" . str_replace("\n", "\\n", $event->getDescription()) . "\r\n";
        }

        if ($event->getStartDate()) {
            $vcal .= "DTSTART:" . $event->getStartDate()->format('Ymd\THis') . "\r\n";
        }

        if ($event->getEndDate()) {
            $vcal .= "DTEND:" . $event->getEndDate()->format('Ymd\THis') . "\r\n";
        }

        if ($event->getColor()) {
            $vcal .= "CATEGORIES:" . $event->getColor() . "\r\n";
        }

        $vcal .= "DTSTAMP:" . (new \DateTime())->format('Ymd\THis') . "\r\n";
        $vcal .= "END:VEVENT\r\n";
        $vcal .= "END:VCALENDAR\r\n";

        return $vcal;
    }

    private function getDefaultUser(): User
    {
        $users = $this->userRepository->findAll();
        return $users[0] ?? throw new \RuntimeException('No users found');
    }
}
