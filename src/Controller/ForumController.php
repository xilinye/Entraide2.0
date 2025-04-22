<?php

namespace App\Controller;

use App\Entity\{Forum, ForumResponse, Rating};
use App\Form\{ForumType, SearchForumType, ForumResponseType, RatingType};
use App\Repository\{ForumRepository, RatingRepository, ForumResponseRepository};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/forum', name: 'app_forum_')]
class ForumController extends AbstractController
{

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ForumRepository $forumRepository,
        private readonly RatingRepository $ratingRepository
    ) {}

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

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Forum $forum, EntityManagerInterface $em): Response
    {
        if ($forum->getAuthor() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(ForumType::class, $forum);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'La discussion a été modifiée avec succès.');
            return $this->redirectToRoute('app_forum_show', ['id' => $forum->getId()]);
        }

        return $this->render('forum/edit.html.twig', [
            'form' => $form->createView(),
            'forum' => $forum,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Forum $forum, EntityManagerInterface $em): Response
    {
        if ($forum->getAuthor() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($forum);
        $em->flush();

        $this->addFlash('success', 'La discussion a été supprimée avec succès.');
        return $this->redirectToRoute('app_forum_index');
    }

    #[Route('/{id}', name: 'show')]
    public function show(Forum $forum, ForumResponseRepository $forumResponseRepo, Request $request): Response
    {
        $response = new ForumResponse();
        $responseForm = $this->createForm(ForumResponseType::class, $response);
        $responseForm->handleRequest($request);

        if ($responseForm->isSubmitted() && $responseForm->isValid()) {
            $response->setForum($forum)
                ->setAuthor($this->getUser());

            $this->em->persist($response);
            $this->em->flush();

            $this->addFlash('success', 'Réponse publiée !');
            return $this->redirectToRoute('app_forum_show', ['id' => $forum->getId()]);
        }

        $responses = $forum->getResponses();
        $responseForms = [];

        foreach ($responses as $forumResponse) {
            $rating = $this->em->getRepository(Rating::class)->findOneBy([
                'forumResponse' => $forumResponse,
                'rater' => $this->getUser()
            ]) ?? new Rating();

            $responseForms[$forumResponse->getId()] = $this->createForm(
                RatingType::class,
                $rating,
                ['action' => $this->generateUrl('app_forum_rate_response', ['id' => $forumResponse->getId()])]
            )->createView();
        }

        $topResponses = $forumResponseRepo->getTopForumResponses($forum);

        return $this->render('forum/show.html.twig', [
            'forum' => $forum,
            'responseForm' => $responseForm->createView(),
            'responses' => $responses,
            'responseForms' => $responseForms,
            'topResponses' => $topResponses
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

    #[Route('/rate-response/{id}', name: 'rate_response', methods: ['POST'])]
    public function rateResponse(ForumResponse $response, Request $request): Response
    {
        if ($response->getAuthor() === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas noter votre propre réponse');
            return $this->redirectToRoute('app_forum_show', ['id' => $response->getForum()->getId()]);
        }

        // Crée le Rating avec les associations AVANT le formulaire
        $rating = new Rating();
        $rating->setForumResponse($response)
            ->setRater($this->getUser())
            ->setRatedUser($response->getAuthor());

        $form = $this->createForm(RatingType::class, $rating);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($rating);
            $this->em->flush();
            $this->addFlash('success', 'Merci pour votre notation !');
        } else {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->redirectToRoute('app_forum_show', ['id' => $response->getForum()->getId()]);
    }
}
