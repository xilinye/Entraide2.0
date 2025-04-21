<?php

namespace App\Controller;

use App\Entity\Event;
use App\Form\EventType;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/evenement', name: 'app_event_')]
#[IsGranted('ROLE_USER')]
class EventController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(EventRepository $eventRepository): Response
    {
        return $this->render('event/index.html.twig', [
            'upcoming_events' => $eventRepository->findUpcoming(),
            'past_events' => $eventRepository->findPast(),
        ]);
    }

    #[Route('/nouveau', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $event = new Event();
        $event->setOrganizer($this->getUser());

        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($event);
            $entityManager->flush();

            $this->addFlash('success', 'Événement créé avec succès');
            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        return $this->render('event/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Event $event): Response
    {
        return $this->render('event/show.html.twig', [
            'event' => $event,
            'is_registered' => $event->getAttendees()->contains($this->getUser()),
            'attendees' => $event->getSortedAttendees()
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    #[IsGranted('edit', 'event')]
    public function edit(Request $request, Event $event, EntityManagerInterface $entityManager): Response
    {
        if ($event->isPast()) {
            $this->addFlash('error', 'Impossible de modifier un événement terminé');
            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Événement mis à jour');
            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        return $this->render('event/edit.html.twig', [
            'event' => $event,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/inscription', name: 'register', methods: ['POST'])]
    public function register(Event $event, EntityManagerInterface $entityManager): Response
    {
        if ($event->isPast()) {
            $this->addFlash('error', 'Impossible de s\'inscrire à un événement terminé');
            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $user = $this->getUser();
        if (!$event->canRegister($user)) {
            $this->addFlash('error', 'Inscription impossible à cet événement');
            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $event->addAttendee($user);
        $entityManager->flush();

        $this->addFlash('success', 'Inscription confirmée');
        return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
    }

    #[Route('/{id}/desinscription', name: 'unregister', methods: ['POST'])]
    public function unregister(Event $event, EntityManagerInterface $entityManager): Response
    {
        if ($event->isPast()) {
            $this->addFlash('error', 'Impossible de se désinscrire d\'un événement terminé');
            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $event->removeAttendee($this->getUser());
        $entityManager->flush();

        $this->addFlash('success', 'Désinscription effectuée');
        return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
    }

    #[Route('/{id}/supprimer', name: 'delete', methods: ['POST'])]
    #[IsGranted('delete', 'event')]
    public function delete(Request $request, Event $event, EntityManagerInterface $entityManager): Response
    {
        if ($event->isPast()) {
            $this->addFlash('error', 'Impossible de supprimer un événement terminé');
            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        if ($this->isCsrfTokenValid('delete' . $event->getId(), $request->request->get('_token'))) {
            $entityManager->remove($event);
            $entityManager->flush();

            $this->addFlash('success', 'Événement supprimé');
        }

        return $this->redirectToRoute('app_event_index');
    }
}
