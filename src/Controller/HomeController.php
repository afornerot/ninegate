<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\PageParameterBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private PageParameterBag $pageParameterBag,
    ) {}

    #[Route('/', name: 'app_home')]
    public function home(): Response
    {
        $user = $this->getUser();
        if ($user) {
            $this->pageParameterBag->load();
            
            $pages = [];
            if (!empty($this->pageParameterBag->get('group_orga_role'))) {
                $pages = $this->pageParameterBag->get('group_orga_role');
            } elseif (!empty($this->pageParameterBag->get('personal'))) {
                $pages = $this->pageParameterBag->get('personal');
            } elseif (!empty($this->pageParameterBag->get('work_group'))) {
                $pages = $this->pageParameterBag->get('work_group');
            }
            
            if (!empty($pages)) {
                $firstPage = $pages[0];
                return $this->redirectToRoute('app_page_view', ['slug' => $firstPage->getSlug()]);
            }
        }
        
        return $this->render('home/home.html.twig', [
            'usemenu' => true,
            'usesidebar' => false,
            'title' => 'Accueil',
        ]);
    }

    #[Route('/admin', name: 'app_admin')]
    public function admin(): Response
    {
        return $this->render('home/admin.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Administration',
        ]);
    }

    #[Route('/user', name: 'app_user')]
    public function user(): Response
    {
        $user = $this->userRepository->find($this->getUser());

        return $this->render('home/user.html.twig', [
            'usemenu' => true,
            'usesidebar' => true,
            'title' => 'Mon Espace',
            'user' => $user,
        ]);
    }
}
