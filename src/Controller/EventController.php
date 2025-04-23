<?php

namespace App\Controller;

use App\Entity\{Event, Rating};
use App\Form\{EventType, RatingType};
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
            // Gestion de l'image
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $newFilename = uniqid() . '.' . $imageFile->guessExtension();
                $imageFile->move(
                    $this->getParameter('event_images_directory'),
                    $newFilename
                );
                $event->setImageName($newFilename);
            }
            $entityManager->persist($event);
            $entityManager->flush();

            $this->addFlash('success', 'Événement créé avec succès');
            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        return $this->render('event/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET', 'POST'])]
    public function show(Event $event, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $ratingRepo = $em->getRepository(Rating::class);

        // Vérification si l'utilisateur a déjà noté
        $existingRating = $user ? $ratingRepo->findOneBy([
            'rater' => $user,
            'event' => $event
        ]) : null;

        $rating = $existingRating ?? new Rating();
        $form = $this->createForm(RatingType::class, $rating);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Validation des conditions
                if (!$event->isPast()) {
                    throw new \Exception('Vous ne pouvez noter que les événements passés');
                }

                if (!$event->getAttendees()->contains($user)) {
                    throw new \Exception('Seuls les participants peuvent noter cet événement');
                }

                // Gestion de la notation existante
                if (!$existingRating) {
                    $rating->setRater($user)
                        ->setEvent($event)
                        ->setRatedUser($event->getOrganizer()); // Important pour la relation
                }

                $em->persist($rating);
                $em->flush();

                $this->addFlash('success', $existingRating ? 'Note mise à jour !' : 'Merci pour votre notation !');
                return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
                return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
            }
        }

        return $this->render('event/show.html.twig', [
            'event' => $event,
            'is_registered' => $event->getAttendees()->contains($user),
            'attendees' => $event->getSortedAttendees(),
            'ratingForm' => $form->createView(),
            'averageRating' => $ratingRepo->getAverageForEvent($event),
            'ratings' => $ratingRepo->findBy(['event' => $event], ['createdAt' => 'DESC']),
            'canRate' => $user && $event->isPast() && $event->getAttendees()->contains($user),
            'existingRating' => $existingRating
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

        $originalImage = $event->getImageName();
        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Gestion de l'image
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                // Suppression ancienne image
                if ($originalImage) {
                    $oldImagePath = $this->getParameter('event_images_directory') . '/' . $originalImage;
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }
                // Upload nouvelle image
                $newFilename = uniqid() . '.' . $imageFile->guessExtension();
                $imageFile->move(
                    $this->getParameter('event_images_directory'),
                    $newFilename
                );
                $event->setImageName($newFilename);
            }
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
            // Suppression de l'image
            if ($event->getImageName()) {
                $imagePath = $this->getParameter('event_images_directory') . '/' . $event->getImageName();
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
            $entityManager->remove($event);
            $entityManager->flush();

            $this->addFlash('success', 'Événement supprimé');
        }

        return $this->redirectToRoute('app_event_index');
    }
}
