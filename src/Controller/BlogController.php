<?php

namespace App\Controller;

use App\Entity\Blog;
use App\Form\BlogType;
use App\Repository\BlogRepository;
use App\Voter\BlogVoter;
use App\Service\SlugService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BlogController extends AbstractController
{
    private const BLOG_PREFIX = '/blog';
    private const ROUTE_PREFIX_USER = 'app_user_blog';
    private const ROUTE_PREFIX_ADMIN = 'app_admin_blog';

    public function __construct(
        private EntityManagerInterface $em,
        private BlogRepository $blogRepository,
        private SlugService $slugService,
    ) {
    }

    private function getListRoute(bool $isAdmin): string
    {
        return $isAdmin ? self::ROUTE_PREFIX_ADMIN.'_list' : self::ROUTE_PREFIX_USER.'_list';
    }

    #[Route('/user'.self::BLOG_PREFIX, name: 'app_user_blog_list')]
    #[Route('/admin'.self::BLOG_PREFIX, name: 'app_admin_blog_list')]
    public function list(?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');

        if ($isAdmin) {
            $blogs = $this->blogRepository->findAll();
        } else {
            $user = $this->getUser();
            $blogs = $this->blogRepository->findAccessibleBlogs($user);
        }

        return $this->render('blog/list.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => $isAdmin ? 'Liste des Blogs' : 'Mes Blogs',
            'blogs' => $blogs,
            'isAdmin' => $isAdmin,
            'routesubmit' => $isAdmin ? 'app_admin_blog_submit' : 'app_user_blog_submit',
            'routeupdate' => $isAdmin ? 'app_admin_blog_update' : 'app_user_blog_update',
        ]);
    }

    #[Route('/user'.self::BLOG_PREFIX.'/submit', name: 'app_user_blog_submit')]
    #[Route('/admin'.self::BLOG_PREFIX.'/submit', name: 'app_admin_blog_submit')]
    public function submit(Request $request, ?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');
        $listRoute = $this->getListRoute($isAdmin);
        $user = $this->getUser();

        $blog = new Blog();
        $formOptions = ['mode' => 'submit', 'isAdmin' => $isAdmin];

        if (!$isAdmin) {
            $formOptions['user'] = $user;
        }

        $form = $this->createForm(BlogType::class, $blog, $formOptions);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $blog->setSlug($this->slugService->generateUniqueSlug($blog->getTitle(), 'Blog'));

            $this->em->persist($blog);
            $this->em->flush();

            return $this->redirectToRoute($listRoute);
        }

        return $this->render('blog/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Création Blog',
            'form' => $form,
            'routecancel' => $listRoute,
            'routedelete' => $isAdmin ? 'app_admin_blog_delete' : 'app_user_blog_delete',
            'blog' => $blog,
            'isAdmin' => $isAdmin,
        ]);
    }

    #[Route('/user'.self::BLOG_PREFIX.'/update/{id}', name: 'app_user_blog_update')]
    #[Route('/admin'.self::BLOG_PREFIX.'/update/{id}', name: 'app_admin_blog_update')]
    public function update(int $id, Request $request, ?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');
        $listRoute = $this->getListRoute($isAdmin);

        $blog = $this->blogRepository->find($id);
        if (!$blog) {
            return $this->redirectToRoute($listRoute);
        }

        if (!$this->isGranted(BlogVoter::EDIT, $blog)) {
            return $this->redirectToRoute($listRoute);
        }

        $formOptions = ['mode' => 'update', 'isAdmin' => $isAdmin];

        $form = $this->createForm(BlogType::class, $blog, $formOptions);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $newSlug = $this->slugService->generateUniqueSlug($blog->getTitle(), 'Blog', $blog->getId());
            if ($newSlug !== $blog->getSlug()) {
                $blog->setSlug($newSlug);
            }
            $this->em->flush();

            return $this->redirectToRoute($listRoute);
        }

        return $this->render('blog/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Modification Blog : '.$blog->getTitle(),
            'form' => $form,
            'routecancel' => $listRoute,
            'routedelete' => $isAdmin ? 'app_admin_blog_delete' : 'app_user_blog_delete',
            'blog' => $blog,
            'isAdmin' => $isAdmin,
        ]);
    }

    #[Route('/user'.self::BLOG_PREFIX.'/delete/{id}', name: 'app_user_blog_delete')]
    #[Route('/admin'.self::BLOG_PREFIX.'/delete/{id}', name: 'app_admin_blog_delete')]
    public function delete(int $id, ?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');
        $listRoute = $this->getListRoute($isAdmin);

        $blog = $this->blogRepository->find($id);
        if (!$blog) {
            return $this->redirectToRoute($listRoute);
        }

        if (!$this->isGranted(BlogVoter::DELETE, $blog)) {
            return $this->redirectToRoute($listRoute);
        }

        try {
            $this->em->remove($blog);
            $this->em->flush();
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute($listRoute);
    }

    #[Route('/blog/{slug}', name: 'app_blog_view')]
    #[Route('/user'.self::BLOG_PREFIX.'/view/{slug}', name: 'app_user_blog_view')]
    #[Route('/admin'.self::BLOG_PREFIX.'/view/{slug}', name: 'app_admin_blog_view')]
    public function view(string $slug, ?string $_route = null): Response
    {
        $isAdmin = $_route && str_starts_with($_route, 'app_admin');

        $blog = $this->blogRepository->findOneBy(['slug' => $slug]);
        if (!$blog) {
            throw $this->createNotFoundException('Blog non trouvé');
        }

        if (!$this->isGranted(BlogVoter::VIEW, $blog)) {
            throw $this->createAccessDeniedException('Accès refusé');
        }

        $articles = $blog->getArticles()->toArray();
        usort($articles, fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());

        return $this->render('blog/view.html.twig', [
            'usemenu' => true,
            'usesidebar' => $isAdmin,
            'title' => $blog->getTitle(),
            'blog' => $blog,
            'articles' => $articles,
            'isAdmin' => $isAdmin,
            'routearticle_submit' => $isAdmin ? 'app_admin_blogarticle_submit' : 'app_user_blogarticle_submit',
        ]);
    }
}
