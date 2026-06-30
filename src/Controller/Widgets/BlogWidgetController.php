<?php

namespace App\Controller\Widgets;

use App\Repository\BlogArticleRepository;
use App\Repository\BlogRepository;
use App\Repository\PageWidgetRepository;
use App\Voter\WidgetVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BlogWidgetController extends AbstractController
{
    public function __construct(
        private BlogArticleRepository $blogArticleRepository,
        private BlogRepository $blogRepository,
    ) {
    }

    #[Route('/user/widget/blog/{pageWidgetId}', name: 'app_user_pagewidget_blog')]
    #[Route('/admin/widget/blog/{pageWidgetId}', name: 'app_admin_pagewidget_blog')]
    public function __invoke(int $pageWidgetId, PageWidgetRepository $pageWidgetRepository, ?string $_route): Response
    {
        $isAdmin = str_starts_with($_route, 'app_admin');

        $pageWidget = $pageWidgetRepository->find($pageWidgetId);
        if (!$pageWidget) {
            return new Response('');
        }

        $canManage = $this->isGranted(WidgetVoter::CAN_MANAGE, $pageWidget);

        $content = $pageWidget->getContent() ?? [];
        $config = $pageWidget->getWidget()->getConfig() ?? [];

        $nbArticles = $content['nbArticles'] ?? $config['nbArticles']['default'] ?? 10;
        $mode = $content['mode'] ?? $config['mode']['default'] ?? 'all';

        $user = $this->getUser();

        if ($mode === 'linked') {
            $page = $pageWidget->getPage();
            $pageGroups = $page->getGroups()->toArray();
            $blogs = $this->blogRepository->findBlogsByGroups($pageGroups);
            $articles = $this->blogArticleRepository->findArticlesByBlogs($blogs, $user, $nbArticles);
        } else {
            $articles = $this->blogArticleRepository->findAccessibleArticles($user, $nbArticles);
        }

        return $this->render('widget/blog.html.twig', [
            'pageWidget' => $pageWidget,
            'canManage' => $canManage,
            'articles' => $articles,
            'isAdmin' => $isAdmin,
        ]);
    }
}
