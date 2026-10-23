<?php

namespace App\Security;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Porte l'URL d'autorisation OIDC : AuthenticatorInterface::authenticate() ne
 * pouvant pas renvoyer de Response, la redirection vers le fournisseur passe
 * par cette exception, traitée dans onAuthenticationFailure().
 */
class OidcRedirectException extends AuthenticationException
{
    public function __construct(
        private string $authorizationUrl,
    ) {
        parent::__construct('OIDC: redirection vers le fournisseur d\'identité.');
    }

    public function getAuthorizationUrl(): string
    {
        return $this->authorizationUrl;
    }
}
