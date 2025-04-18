<?php

namespace App\Controller;

use App\Entity\{Forum, ForumResponse};
use App\Form\{ForumType, SearchForumType, ForumResponseType};
use App\Repository\ForumRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/forum', name: 'app_forum_')]
class ForumController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(Request $request, ForumRepository $forumRepository): Response
    {
        $searchForm = $this->createForm(SearchForumType::class);
        $searchForm->handleRequest($request);

        $forums = $forumRepository->searchByQueryAndCategory(
            $searchForm->get('query')->getData(),
            $searchForm->get('category')->getData()
        );

        return $this->render('forum/index.html.twig', [
            'forums' => $forums,
            'searchForm' => $searchForm->createView()
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $forum = new Forum();
        $form = $this->createForm(ForumType::class, $forum);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $forum->setAuthor($this->getUser());
            $em->persist($forum);
            $em->flush();

            return $this->redirectToRoute('app_forum_index');
        }

        return $this->render('forum/new.html.twig', [
            'form' => $form->createView()
        ]);
    }

    #[Route('/{id}', name: 'show')]
    public function show(Forum $forum, Request $request, EntityManagerInterface $em): Response
    {
        $response = new ForumResponse();
        $form = $this->createForm(ForumResponseType::class, $response);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $response->setAuthor($this->getUser())
                ->setForum($forum);

            $em->persist($response);
            $em->flush();

            return $this->redirectToRoute('app_forum_show', ['id' => $forum->getId()]);
        }

        return $this->render('forum/show.html.twig', [
            'forum' => $forum,
            'responseForm' => $form->createView()
        ]);
    }
    #[Route('/{id}/toggle', name: 'toggle', methods: ['POST'])]
    public function toggle(Forum $forum, EntityManagerInterface $em): Response
    {
        if ($forum->getAuthor() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $forum->setIsOpen(!$forum->isOpen());
        $em->flush();

        return $this->redirectToRoute('app_forum_show', ['id' => $forum->getId()]);
    }
}
