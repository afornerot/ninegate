<?php

namespace App\Repository;

use App\Entity\Blog;
use App\Entity\Group;
use App\Entity\User;
use App\Entity\UserGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Blog>
 */
class BlogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Blog::class);
    }

    public function findAccessibleBlogs(?User $user): array
    {
        $blogs = $this->findAll();

        return array_filter($blogs, fn ($blog) => $this->isBlogAccessibleForUser($blog, $user));
    }

    public function isBlogAccessibleForUser(Blog $blog, ?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if (!empty($blog->getRoles())) {
            foreach ($user->getRoles() as $userRole) {
                if (in_array($userRole, $blog->getRoles())) {
                    return true;
                }
            }
        }

        foreach ($blog->getGroups() as $group) {
            $userGroup = $group->getUserGroup($user);
            if ($userGroup) {
                return true;
            }
        }

        return false;
    }

    public function isBlogOwnerOrGroupMaster(Blog $blog, User $user): bool
    {
        $blogGroups = $blog->getGroups();
        foreach ($blogGroups as $group) {
            $userGroup = $group->getUserGroup($user);
            if ($userGroup && UserGroup::ROLE_MASTER === $userGroup->getRole()) {
                return true;
            }
        }

        return false;
    }

    public function isBlogUserOrAbove(Blog $blog, User $user): bool
    {
        foreach ($blog->getGroups() as $group) {
            $userGroup = $group->getUserGroup($user);
            if ($userGroup && in_array($userGroup->getRole(), [UserGroup::ROLE_MASTER, UserGroup::ROLE_USER])) {
                return true;
            }
        }

        return false;
    }

    public function findBlogsByGroups(array $groups): array
    {
        if (empty($groups)) {
            return [];
        }

        $groupIds = array_map(fn(Group $g) => $g->getId(), $groups);

        return $this->createQueryBuilder('b')
            ->innerJoin('b.groups', 'g')
            ->where('g.id IN (:groupIds)')
            ->setParameter('groupIds', $groupIds)
            ->getQuery()
            ->getResult();
    }
}
