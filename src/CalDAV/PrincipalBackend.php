<?php

namespace App\CalDAV;

use App\Repository\UserRepository;
use Sabre\DAV\PropPatch;
use Sabre\DAVACL\PrincipalBackend\BackendInterface;

class PrincipalBackend implements BackendInterface
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    public function getPrincipalsByPrefix($prefixPath): array
    {
        $users = $this->userRepository->findAll();
        $result = [];

        foreach ($users as $user) {
            $username = $user->getUsername();
            $uri = $prefixPath . '/' . $username;
            $result[$uri] = $this->mapUser($user, $uri);
        }

        return $result;
    }

    public function getPrincipalByPath($path): ?array
    {
        $username = $this->extractUsername($path);
        if (!$username) {
            return null;
        }

        $user = $this->userRepository->findOneBy(['username' => $username]);
        if (!$user) {
            return null;
        }

        return $this->mapUser($user, $path);
    }

    public function updatePrincipal($path, PropPatch $propPatch): void
    {
    }

    public function searchPrincipals($prefixPath, array $searchProperties, $test = 'allof'): array
    {
        return [];
    }

    public function findByUri($uri, $principalPrefix): ?string
    {
        if (str_starts_with($uri, 'mailto:')) {
            $email = substr($uri, 7);
            $user = $this->userRepository->findOneBy(['email' => $email]);
            if ($user) {
                return $principalPrefix . '/' . $user->getUsername();
            }
        }

        return null;
    }

    public function getGroupMemberSet($principal): array
    {
        return [];
    }

    public function getGroupMembership($principal): array
    {
        return [];
    }

    public function setGroupMemberSet($principal, array $members): void
    {
    }

    private function mapUser(object $user, string $uri): array
    {
        return [
            'id' => $uri,
            'uri' => $uri,
            '{DAV:}displayname' => $user->getDisplayName(),
            '{http://sabredav.org/ns}email-address' => $user->getEmail() ?? '',
        ];
    }

    private function extractUsername(string $path): ?string
    {
        $parts = explode('/', $path);
        return end($parts) ?: null;
    }
}
