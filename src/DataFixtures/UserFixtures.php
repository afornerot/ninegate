<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public function __construct(
        private ParameterBagInterface $parameterBag,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $data = [
            ['username' => 'admin', 'email' => 'admin@example.com', 'role' => 'ROLE_ADMIN'],
            ['username' => 'master', 'email' => 'master@example.com', 'role' => 'ROLE_MASTER'],
            ['username' => 'user', 'email' => 'user@example.com', 'role' => 'ROLE_USER'],
        ];

        foreach ($data as $item) {
            $user = $this->userRepository->findOneBy(['username' => $item['username']]);
            if (!$user) {
                $user = new User();
                $user->setUsername($item['username']);
                $user->setEmail($item['email']);
                $hashedPassword = $this->passwordHasher->hashPassword($user, $this->parameterBag->get('appSecret'));
                $user->setPassword($hashedPassword);
                $user->setRoles([$item['role']]);
            }

            $manager->persist($user);
        }

        $manager->flush();
    }
}
