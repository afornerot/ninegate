<?php

namespace App\Controller;

use App\Entity\BlogArticle;
use App\Form\BlogArticleType;
use App\Repository\BlogArticleRepository;
use App\Repository\BlogRepository;
use App\Service\SlugService;
use App\Voter\BlogVoter;
use Bnine\FilesBundle\Service\FileService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BlogArticleController extends AbstractController
{
    private const BLOG_PREFIX = '/blog';
    private const ROUTE_PREFIX_USER = 'app_user_blogarticle';
    private const ROUTE_PREFIX_ADMIN = 'app_admin_blogarticle';

    public function __construct(
        private EntityManagerInterface $em,
        private BlogArticleRepository $blogArticleRepository,
        private BlogRepository $blogRepository,
        private SlugService $slugService,
        private \Bnine\FilesBundle\Service\FileService $fileService,
    ) {
    }

    #[Route('/user'.self::BLOG_PREFIX.'/article/submit/{blogId}', name: 'app_user_blogarticle_submit')]
    #[Route('/admin'.self::BLOG_PREFIX.'/article/submit/{blogId}', name: 'app_admin_blogarticle_submit')]
    public function submit(int $blogId, Request $request, ?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');

        $blog = $this->blogRepository->find($blogId);
        if (!$blog) {
            return $this->redirectToRoute($isAdmin ? 'app_admin_blog_list' : 'app_user_blog_list');
        }

        $article = new BlogArticle();
        $article->setBlog($blog);
        $article->setUser($this->getUser());

        $form = $this->createForm(BlogArticleType::class, $article, [
            'articleId' => 0,
            'blogId' => $blog->getId(),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $article->setSlug($this->slugService->generateUniqueSlug($article->getTitle(), 'BlogArticle'));
            $article->setUpdatedAt(new \DateTime());

            $this->em->persist($article);
            $this->em->flush();

            $this->fileService->init('blogarticle', (string) $article->getId());

            return $this->redirectToRoute('app_blog_view', ['slug' => $blog->getSlug()]);
        }

        return $this->render('blog/article/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Nouvel article',
            'form' => $form,
            'blog' => $blog,
            'article' => $article,
            'isAdmin' => $isAdmin,
            'domain' => 'blogarticle',
            'id' => $article->getId(),
            'editable' => 1,
        ]);
    }

    #[Route('/user'.self::BLOG_PREFIX.'/article/uploadmodal/{blogId}/{id}', name: 'app_user_blogarticle_uploadmodal')]
    #[Route('/admin'.self::BLOG_PREFIX.'/article/uploadmodal/{blogId}/{id}', name: 'app_admin_blogarticle_uploadmodal')]
    public function uploadmodal(int $blogId, int $id, Request $request): Response
    {
        $article = $this->blogArticleRepository->find($id);
        if (!$article) {
            return $this->redirectToRoute('app_admin_blog_list');
        }

        return $this->render('blog/article/upload.html.twig', [
            'useheader' => false,
            'usemenu' => false,
            'usesidebar' => false,
            'endpoint' => 'bninefile',
            'domain' => 'blogarticle',
            'id' => $id,
            'path' => '',
        ]);
    }

    #[Route('/user'.self::BLOG_PREFIX.'/article/update/{blogId}/{id}', name: 'app_user_blogarticle_update')]
    #[Route('/admin'.self::BLOG_PREFIX.'/article/update/{blogId}/{id}', name: 'app_admin_blogarticle_update')]
    public function update(int $blogId, int $id, Request $request, ?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');

        $article = $this->blogArticleRepository->find($id);
        if (!$article) {
            return $this->redirectToRoute($isAdmin ? 'app_admin_blog_list' : 'app_user_blog_list');
        }

        $blog = $article->getBlog();

        $form = $this->createForm(BlogArticleType::class, $article, [
            'articleId' => $id,
            'blogId' => $blog->getId(),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($article->getTitle() !== $article->getSlug()) {
                $article->setSlug($this->slugService->generateUniqueSlug($article->getTitle(), 'BlogArticle', $article->getId()));
            }
            $article->setUpdatedAt(new \DateTime());

            $this->em->flush();

            return $this->redirectToRoute('app_blog_view', ['slug' => $blog->getSlug()]);
        }

        return $this->render('blog/article/edit.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Modifier l\'article',
            'form' => $form,
            'blog' => $blog,
            'article' => $article,
            'isAdmin' => $isAdmin,
        ]);
    }

    #[Route('/user'.self::BLOG_PREFIX.'/article/delete/{blogId}/{id}', name: 'app_user_blogarticle_delete')]
    #[Route('/admin'.self::BLOG_PREFIX.'/article/delete/{blogId}/{id}', name: 'app_admin_blogarticle_delete')]
    public function delete(int $blogId, int $id, ?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');

        $article = $this->blogArticleRepository->find($id);
        if (!$article) {
            return $this->redirectToRoute($isAdmin ? 'app_admin_blog_list' : 'app_user_blog_list');
        }

        $blog = $article->getBlog();

        try {
            $this->em->remove($article);
            $this->em->flush();
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_blog_view', ['slug' => $blog->getSlug()]);
    }

    #[Route('/article/{slug}', name: 'app_blogarticle_view')]
    #[Route('/admin/blog/article/view/{slug}', name: 'app_admin_blogarticle_view')]
    public function view(string $slug, Request $request, ?string $_route = null): Response
    {
        $isAdmin = $_route && str_starts_with($_route, 'app_admin');

        $article = $this->blogArticleRepository->findOneBy(['slug' => $slug]);
        if (!$article) {
            throw $this->createNotFoundException('Article non trouvé');
        }

        $blog = $article->getBlog();
        $pageSlug = $request->query->get('pageSlug');

        return $this->render('blog/article/view.html.twig', [
            'usemenu' => true,
            'usesidebar' => $isAdmin,
            'title' => $article->getTitle(),
            'article' => $article,
            'blog' => $blog,
            'isAdmin' => $isAdmin,
            'maxwidth' => 1100,
            'pageSlug' => $pageSlug,
        ]);
    }
}
