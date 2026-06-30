<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserProvider implements UserProviderInterface
{
    private UserRepository $userRepository;
    private EntityManagerInterface $em;
    private ParameterBagInterface $parameterBag;

    public function __construct(UserRepository $userRepository, EntityManagerInterface $em, ParameterBagInterface $parameterBag)
    {
        $this->userRepository = $userRepository;
        $this->em = $em;
        $this->parameterBag = $parameterBag;
    }

    /**
     * Charge un utilisateur par son identifiant CAS.
     */
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        // Récupérer l'utilisateur depuis la base de données
        $user = $this->userRepository->findOneBy(['username' => $identifier]);

        if (!$user) {
            throw new UserNotFoundException(sprintf('User with username "%s" not found.', $identifier));
        }

        return $user;
    }

    /**
     * Charge un utilisateur par son identifiant CAS et autosubmit autoupdate en fonction des attributs CAS.
     */
    public function loadUserByIdentifierAndCASAttributes(string $identifier, array $attributes): UserInterface
    {
        // Charger l'utilisateur existant depuis la base de données
        $user = $this->userRepository->findOneBy(['username' => $identifier]);

        if (!$user) {
            // Créer un nouvel utilisateur avec les attributs CAS
            $user = new User();
            $user->setUsername($identifier);
            $user->setPassword(Uuid::uuid4()->toString());
            $user->setRoles(['ROLE_USER']);

            // Persister l'utilisateur en base si nécessaire
            $this->em->persist($user);
        }

        $user->setEmail($attributes[$this->parameterBag->get('casMail')] ?? null);
        $this->em->flush();

        return $user;
    }

    /**
     * Charge un utilisateur par son identifiant CAS et autosubmit autoupdate en fonction des attributs CAS.
     */
    public function loadUserByIdentifierAndOIDCAttributes(string $identifier, array $attributes): UserInterface
    {
        // Charger l'utilisateur existant depuis la base de données
        $user = $this->userRepository->findOneBy(['username' => $identifier]);

        if (!$user) {
            // Créer un nouvel utilisateur avec les attributs CAS
            $user = new User();
            $user->setUsername($identifier);
            $user->setPassword(Uuid::uuid4()->toString());
            $user->setRoles(['ROLE_USER']);

            // Persister l'utilisateur en base si nécessaire
            $this->em->persist($user);
        }

        $user->setEmail($attributes[$this->parameterBag->get('oidcMailAttribute')] ?? null);
        $this->em->flush();

        return $user;
    }

    /**
     * Permet de recharger un utilisateur déjà authentifié (si nécessaire).
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new \InvalidArgumentException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        // Recharge l'utilisateur en base si nécessaire (ex. : rôles mis à jour)
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
