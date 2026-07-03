<?php

namespace App\DataFixtures;

use App\Entity\Group;
use App\Repository\GroupRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class GroupFixtures extends Fixture
{
    public const TOUS_REF = 'tous';

    public function __construct(
        private GroupRepository $groupRepository,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $tous = $this->groupRepository->findOneBy(['slug' => 'all']);
        if (!$tous) {
            $tous = new Group();
            $tous->setName('Tous');
            $tous->setSlug('all');
            $tous->setType(Group::TYPE_ORGANISATION);
            $tous->setIsOpen(false);
            $tous->setIsSystem(true);
            $tous->setDescription('Groupe contenant tous les utilisateurs');

            $manager->persist($tous);
            $manager->flush();
        }

        $this->addReference(self::TOUS_REF, $tous);
    }
}
