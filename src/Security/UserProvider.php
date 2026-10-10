<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\IdentityProvider;
use App\Service\UsernameNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserProvider implements UserProviderInterface
{
    private UserRepository $userRepository;
    private EntityManagerInterface $em;
    private ParameterBagInterface $parameterBag;
    private IdentityProvider $identityProvider;
    private UsernameNormalizer $usernameNormalizer;

    public function __construct(
        UserRepository $userRepository,
        EntityManagerInterface $em,
        ParameterBagInterface $parameterBag,
        IdentityProvider $identityProvider,
        UsernameNormalizer $usernameNormalizer,
    ) {
        $this->userRepository = $userRepository;
        $this->em = $em;
        $this->parameterBag = $parameterBag;
        $this->identityProvider = $identityProvider;
        $this->usernameNormalizer = $usernameNormalizer;
    }

    /**
     * Charge un utilisateur par son identifiant.
     */
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->userRepository->findOneBy(['username' => $identifier]);

        if (!$user) {
            throw new UserNotFoundException(sprintf('User with username "%s" not found.', $identifier));
        }

        return $user;
    }

    /**
     * Charge un utilisateur CAS et synchronise ses données depuis les attributs CAS.
     */
    public function loadUserByIdentifierAndCASAttributes(string $identifier, array $attributes): UserInterface
    {
        try {
            $normalized = $this->usernameNormalizer->normalizeForUsername($identifier);
        } catch (\InvalidArgumentException $e) {
            throw new AuthenticationException(
                sprintf('CAS : identifiant "%s" non normalisable en login ninegate.', $identifier)
            );
        }

        $user = $this->userRepository->findOneBy(['username' => $normalized]);

        if (!$user) {
            $user = new User();
            $user->setUsername($normalized);
            $user->setPassword(Uuid::uuid4()->toString());
            $user->setRoles(['ROLE_USER']);
            $this->em->persist($user);
        }

        if ($this->identityProvider->isMasterSSO()) {
            $this->applyRequiredAttribute($user, $attributes, 'casMail', 'email');
            $this->applyOptionalAttribute($user, $attributes, 'casFirstname', 'firstname');
            $this->applyOptionalAttribute($user, $attributes, 'casLastname', 'lastname');
        } else {
            $this->applyOptionalAttribute($user, $attributes, 'casMail', 'email');
        }

        $this->em->flush();

        return $user;
    }

    /**
     * Charge un utilisateur OIDC et synchronise ses données depuis les attributs OIDC.
     */
    public function loadUserByIdentifierAndOIDCAttributes(string $identifier, array $attributes): UserInterface
    {
        try {
            $normalized = $this->usernameNormalizer->normalizeForUsername($identifier);
        } catch (\InvalidArgumentException $e) {
            throw new AuthenticationException(
                sprintf('OIDC : identifiant "%s" non normalisable en login ninegate.', $identifier)
            );
        }

        $user = $this->userRepository->findOneBy(['username' => $normalized]);

        if (!$user) {
            $user = new User();
            $user->setUsername($normalized);
            $user->setPassword(Uuid::uuid4()->toString());
            $user->setRoles(['ROLE_USER']);
            $this->em->persist($user);
        }

        if ($this->identityProvider->isMasterSSO()) {
            $this->applyRequiredAttribute($user, $attributes, 'oidcMailAttribute', 'email');
            $this->applyOptionalAttribute($user, $attributes, 'oidcFirstnameAttribute', 'firstname');
            $this->applyOptionalAttribute($user, $attributes, 'oidcLastnameAttribute', 'lastname');
        } else {
            $this->applyOptionalAttribute($user, $attributes, 'oidcMailAttribute', 'email');
        }

        return $user;
    }

    /**
     * Applique un setter uniquement si l'attribut est configuré ET présent dans les claims
     * ET non vide. Utilisé pour les attributs optionnels (firstname, lastname).
     */
    private function applyOptionalAttribute(
        User $user,
        array $attributes,
        string $paramKey,
        string $setter
    ): void {
        $attrName = $this->parameterBag->get($paramKey);
        if (!is_string($attrName) || '' === $attrName) {
            return;
        }
        if (!array_key_exists($attrName, $attributes)) {
            return;
        }
        $value = $attributes[$attrName];
        if (null === $value || '' === $value) {
            return;
        }
        $user->{'set'.ucfirst($setter)}($value);
    }

    /**
     * Applique un setter en exigeant que l'attribut soit configuré et présent.
     * Lève une AuthenticationException explicite si manquant, avec un message
     * indiquant comment corriger la config.
     */
    private function applyRequiredAttribute(
        User $user,
        array $attributes,
        string $paramKey,
        string $setter
    ): void {
        $attrName = $this->parameterBag->get($paramKey);
        if (!is_string($attrName) || '' === $attrName) {
            throw new AuthenticationException(sprintf(
                'Configuration invalide : le paramètre "%s" doit être défini.',
                $paramKey
            ));
        }
        if (!isset($attributes[$attrName]) || '' === $attributes[$attrName]) {
            throw new AuthenticationException(sprintf(
                'Authentification impossible : le claim "%s" est manquant ou vide. Votre fournisseur d\'identité doit le fournir.',
                $attrName
            ));
        }
        $user->{'set'.ucfirst($setter)}($attributes[$attrName]);
    }

    /**
     * Permet de recharger un utilisateur déjà authentifié.
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new \InvalidArgumentException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        return $this->userRepository->find($user->getId());
    }

    /**
     * Indique si ce provider supporte un type d'utilisateur donné.
     */
    public function supportsClass(string $class): bool
    {
        return User::class === $class;
    }
}