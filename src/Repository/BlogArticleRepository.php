<?php

namespace App\Repository;

use App\Entity\Blog;
use App\Entity\BlogArticle;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BlogArticle>
 */
class BlogArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlogArticle::class);
    }

    public function findAccessibleArticles(?User $user, int $limit): array
    {
        if (!$user) {
            return [];
        }

        $qb = $this->createQueryBuilder('a')
            ->innerJoin('a.blog', 'b')
            ->leftJoin('b.groups', 'g')
            ->leftJoin('g.userGroups', 'ug')
            ->where('ug.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit);

        $articles = $qb->getQuery()->getResult();

        // Deduplicate (multiple groups can match same blog)
        $seen = [];
        $result = [];
        foreach ($articles as $article) {
            if (!isset($seen[$article->getId()])) {
                $seen[$article->getId()] = true;
                $result[] = $article;
            }
        }

        return $result;
    }

    public function findArticlesByBlogs(array $blogs, ?User $user, int $limit): array
    {
        if (empty($blogs)) {
            return [];
        }

        $blogIds = array_map(fn(Blog $b) => $b->getId(), $blogs);

        $qb = $this->createQueryBuilder('a')
            ->where('a.blog IN (:blogIds)')
            ->setParameter('blogIds', $blogIds)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }
}
