<?php

namespace App\Controller;

use App\Security\DynamicAuthenticator;
use Jumbojett\OpenIDConnectClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class OidcCallbackController extends AbstractController
{
    #[Route('/callback', name: 'app_oidc_callback')]
    public function callback(
        Request $request,
        ParameterBagInterface $parameterBag,
        SessionInterface $session,
    ): RedirectResponse {
        $code = $request->query->get('code');
        $state = $request->query->get('state');

        if (!$code || !$state) {
            throw new AuthenticationException('OIDC callback missing code or state parameter.');
        }

        $oidc = new OpenIDConnectClient(
            $parameterBag->get('oidcIssuer'),
            $parameterBag->get('oidcClientId'),
            $parameterBag->get('oidcClientSecret')
        );

        $oidc->setVerifyPeer(false);
        $oidc->setVerifyHost(false);
        $oidc->setRedirectURL($parameterBag->get('oidcRedirectUri'));

        $oidc->authenticate();

        $session->set('oidc_id_token', $oidc->getIdToken());

        $userInfo = $oidc->requestUserInfo();

        if (!$userInfo) {
            throw new AuthenticationException('OIDC authentication failed: could not retrieve user info.');
        }

        $usernameAttribute = $parameterBag->get('oidcUsernameAttribute');
        $username = $userInfo->{$usernameAttribute} ?? null;

        if (!$username) {
            throw new AuthenticationException(sprintf(
                'OIDC authentication failed: "%s" claim is missing or empty.',
                $usernameAttribute
            ));
        }

        $session->set('_security_main', serialize(
            new \Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken(
                $username,
                null,
                'main',
                [$username]
            )
        ));

        $defaultUri = $parameterBag->get('defaultUri');
        $target = $session->get('_security.target_path') ?? $defaultUri . '/admin';
        $session->remove('_security.target_path');

        return new RedirectResponse($target);
    }
}
