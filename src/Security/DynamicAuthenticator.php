<?php

namespace App\Security;

use App\Repository\UserRepository;
use Jumbojett\OpenIDConnectClient;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
        private ParameterBagInterface $parameterBag)
    {
    }

    public function supports(Request $request): ?bool
    {
        // Vérifie si l'utilisateur est déjà connecté
        if ($request->getSession()->get('_security_main')) {
            return false; // L'utilisateur est déjà authentifié
        }

        // Exclure les routes de login et logout pour éviter les boucles
        $currentPath = $request->getPathInfo();
        if (in_array($currentPath, ['/login', '/logout'])) {
            return false;
        }

        // Authentification requise uniquement pour les routes /admin, /master, /user
        if (str_starts_with($currentPath, '/admin')
            || str_starts_with($currentPath, '/master')
            || str_starts_with($currentPath, '/user')) {
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
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        throw $exception;
    }

    private function authenticateWithSql(Request $request): Passport
    {
        $username = $request->request->get('_username', '');
        $password = $request->request->get('_password', '');

        if (!$username || !$password) {
            throw new AuthenticationException('Username and password are required.');
        }

        // Charger l'utilisateur via Doctrine (table user puis table student)
        $user = $this->userRepository->findOneBy(['username' => $username]);
        if (!$user) {
            throw new AuthenticationException('User not found.');
        }

        return new Passport(
            new UserBadge($username, function ($userIdentifier) {
                $user = $this->userRepository->findOneBy(['username' => $userIdentifier]);

                return $user;
            }),
            new PasswordCredentials($password)
        );
    }

    private function authenticateWithCas(Request $request): Passport
    {
        // Récupérer l'hôte d'origine derrière le reverse proxy
        $host = $request->headers->get('host');
        $scheme = $request->headers->get('X-Forwarded-Proto') ?? $request->getScheme();

        // Construire l'URL
        $url = $scheme.'://'.$host;

        // \phpCAS::setDebug('/tmp/logcas.log');
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
        $oidc = new OpenIDConnectClient(
            $this->parameterBag->get('oidcIssuer'),
            $this->parameterBag->get('oidcClientId'),
            $this->parameterBag->get('oidcClientSecret')
        );

        $oidc->setVerifyPeer(false);
        $oidc->setVerifyHost(false);
        $oidc->setRedirectURL($this->parameterBag->get('oidcRedirectUri'));

        // Lance le processus d'authentification (redirections, etc.)
        $oidc->authenticate();

        // Stocke l'id_token pour la déconnexion future
        $request->getSession()->set('oidc_id_token', $oidc->getIdToken());

        // Récupère les informations de l'utilisateur
        $userInfo = $oidc->requestUserInfo();

        if (!$userInfo) {
            throw new AuthenticationException('OIDC authentication failed: could not retrieve user info.');
        }

        $usernameAttribute = $this->parameterBag->get('oidcUsernameAttribute');
        $username = $userInfo->{$usernameAttribute} ?? null;
        $attributes = (array) $userInfo;

        if (!$username) {
            throw new AuthenticationException(sprintf('OIDC authentication failed: "%s" claim is missing or empty.', $usernameAttribute));
        }

        $userBadge = new UserBadge($username, function ($userIdentifier) use ($attributes) {
            return $this->casUserProvider->loadUserByIdentifierAndOIDCAttributes($userIdentifier, $attributes);
        });

        return new SelfValidatingPassport($userBadge);
    }
}
