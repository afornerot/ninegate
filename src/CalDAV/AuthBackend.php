<?php

namespace App\CalDAV;

use App\Repository\UserRepository;
use Sabre\DAV\Auth\Backend\BackendInterface;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

class AuthBackend implements BackendInterface
{
    private ?string $currentUser = null;

    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    public function check(RequestInterface $request, ResponseInterface $response): array
    {
        $authHeader = $request->getHeader('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Basic ')) {
            return [false, 'Authorization header missing'];
        }

        $decoded = base64_decode(substr($authHeader, 6));
        if ($decoded === false || !str_contains($decoded, ':')) {
            return [false, 'Invalid Authorization header format'];
        }

        [$username, $apiKey] = explode(':', $decoded, 2);

        $user = $this->userRepository->findOneBy(['username' => $username]);
        if (!$user || $user->getApiKey() !== $apiKey) {
            return [false, 'Invalid credentials'];
        }

        $this->currentUser = $username;

        return [true, 'principals/' . $username];
    }

    public function challenge(RequestInterface $request, ResponseInterface $response): void
    {
        $response->addHeader('WWW-Authenticate', 'Basic realm="Ninegate CalDAV"');
    }
}
