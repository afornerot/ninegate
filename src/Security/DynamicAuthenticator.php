<?php

namespace App\Security;

use App\Repository\UserRepository;
use Jumbojett\OpenIDConnectClient;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class DynamicAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private UserRepository $userRepository,
        private UserProvider $casUserProvider,
        private ParameterBagInterface $parameterBag,
        private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function supports(Request $request): ?bool
    {
        if ($request->getSession()->get('_security_main')) {
            return false;
        }

        $currentPath = $request->getPathInfo();
        if (in_array($currentPath, ['/login', '/logout'])) {
            return false;
        }

        if (str_starts_with($currentPath, '/admin')
            || str_starts_with($currentPath, '/master')
            || str_starts_with($currentPath, '/user')
            || $currentPath === '/callback') {
            return true;
        }

        return false;
    }

    public function authenticate(Request $request): Passport
    {
        switch ($this->parameterBag->get('appModeAuth')) {
            case 'SQL':
                return $this->authenticateWithSql($request);
            case 'CAS':
                return $this->authenticateWithCas($request);
            case 'OIDC':
                return $this->authenticateWithOidc($request);
            default:
                throw new \InvalidArgumentException('Invalid authentication method');
        }
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $target = $request->getSession()->get('_security.target_path')
            ?? $this->parameterBag->get('defaultUri') . '/admin';

        return new RedirectResponse($target);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        error_log('OIDC AUTH FAILURE: ' . $exception->getMessage());
        throw $exception;
    }

    private function authenticateWithSql(Request $request): Passport
    {
        $username = $request->request->get('_username', '');
        $password = $request->request->get('_password', '');

        if (!$username || !$password) {
            throw new AuthenticationException('Username and password are required.');
        }

        $user = $this->userRepository->findOneBy(['username' => $username]);
        if (!$user) {
            throw new AuthenticationException('User not found.');
        }

        return new Passport(
            new UserBadge($username, function ($userIdentifier) {
                return $this->userRepository->findOneBy(['username' => $userIdentifier]);
            }),
            new PasswordCredentials($password)
        );
    }

    private function authenticateWithCas(Request $request): Passport
    {
        $host = $request->headers->get('host');
        $scheme = $request->headers->get('X-Forwarded-Proto') ?? $request->getScheme();
        $url = $scheme.'://'.$host;

        \phpCAS::client(
            CAS_VERSION_2_0,
            $this->parameterBag->get('casHost'),
            (int) $this->parameterBag->get('casPort'),
            $this->parameterBag->get('casPath'),
            $url,
            false);

        \phpCAS::setNoCasServerValidation();
        \phpCAS::forceAuthentication();

        $username = \phpCAS::getUser();
        $attributes = \phpCAS::getAttributes();

        if (!$username) {
            throw new AuthenticationException('CAS authentication failed.');
        }

        $userBadge = new UserBadge($username, function ($userIdentifier) use ($attributes) {
            return $this->casUserProvider->loadUserByIdentifierAndCASAttributes($userIdentifier, $attributes);
        });

        return new SelfValidatingPassport($userBadge);
    }

    private function authenticateWithOidc(Request $request): Passport
    {
        $currentPath = $request->getPathInfo();
        error_log('OIDC AUTH: path=' . $currentPath . ' code=' . ($request->query->get('code') ?: 'none'));

        $oidc = new OpenIDConnectClient(
            $this->parameterBag->get('oidcIssuer'),
            $this->parameterBag->get('oidcClientId'),
            $this->parameterBag->get('oidcClientSecret')
        );

        $oidc->setVerifyPeer(false);
        $oidc->setVerifyHost(false);
        $oidc->setRedirectURL($this->parameterBag->get('oidcRedirectUri'));
        $oidc->addScope(['openid', 'email', 'profile']);

        $oidc->authenticate();

        $idToken = $oidc->getIdToken();
        $request->getSession()->set('oidc_id_token', $idToken);

        // Decode ID token JWT to get claims
        $parts = explode('.', $idToken);
        if (count($parts) === 3) {
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
            $userInfo = (object) $payload;
        } else {
            $userInfo = null;
        }

        error_log('OIDC AUTH: userInfo=' . json_encode($userInfo));

        if (!$userInfo) {
            throw new AuthenticationException('OIDC authentication failed: could not decode ID token.');
        }

        $usernameAttribute = $this->parameterBag->get('oidcUsernameAttribute');
        $username = $userInfo->{$usernameAttribute} ?? null;
        $attributes = (array) $userInfo;

        if (!$username) {
            throw new AuthenticationException(sprintf(
                'OIDC authentication failed: "%s" claim is missing or empty. Available: %s',
                $usernameAttribute,
                implode(', ', array_keys($attributes))
            ));
        }

        error_log('OIDC AUTH: username=' . $username);

        $userBadge = new UserBadge($username, function ($userIdentifier) use ($attributes) {
            return $this->casUserProvider->loadUserByIdentifierAndOIDCAttributes($userIdentifier, $attributes);
        });

        return new SelfValidatingPassport($userBadge);
    }
}
