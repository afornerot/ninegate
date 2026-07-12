<?php

namespace App\DataFixtures;

use App\Entity\Group;
use App\Entity\User;
use App\Repository\GroupRepository;
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
        private GroupRepository $groupRepository,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $all = $this->groupRepository->findOneBy(['slug' => 'all']);

        $admins = array_map('trim', explode(',', $this->parameterBag->get('appAdmin')));
        $data = [];
        foreach ($admins as $admin) {
            $data[] = ['username' => $admin, 'email' => $this->parameterBag->get('appNoreply'), 'role' => 'ROLE_ADMIN', 'avatar' => 'medias/avatar/admin.jpg'];
        }
        $data[] = ['username' => 'master', 'email' => 'master@example.com', 'role' => 'ROLE_MASTER', 'avatar' => null];
        $data[] = ['username' => 'user', 'email' => 'user@example.com', 'role' => 'ROLE_USER', 'avatar' => null];

        foreach ($data as $item) {
            $user = $this->userRepository->findOneBy(['username' => $item['username']]);
            if (!$user) {
                $user = new User();
                $user->setUsername($item['username']);
                $user->setEmail($item['email']);
                $hashedPassword = $this->passwordHasher->hashPassword($user, $this->parameterBag->get('appAdminPassword'));
                $user->setPassword($hashedPassword);
                $user->setSha256Hash(hash('sha256', $this->parameterBag->get('appAdminPassword')));
                $user->setRoles([$item['role']]);
                if ($item['avatar']) {
                    $user->setAvatar($item['avatar']);
                }
                $manager->persist($user);
            }

            if ($all && !$all->getUserGroup($user)) {
                $all->addUser($user);
            }
        }

        $manager->flush();
    }
}
