<?php

namespace App\Controller;

use App\Entity\{User, Message, ConversationDeletion};
use App\Form\MessageType;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Psr\Log\LoggerInterface;

#[Route('/messages', name: 'app_message_')]
#[IsGranted('ROLE_USER')]
class MessageController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(MessageRepository $messageRepository): Response
    {
        $user = $this->getUser();
        $conversations = $messageRepository->findConversations($user);

        return $this->render('message/index.html.twig', [
            'conversations' => $conversations
        ]);
    }

    #[Route('/nouveau/{id}', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        User $receiver,
        Request $request,
        EntityManagerInterface $em,
        MailerInterface $mailer,
        LoggerInterface $logger
    ): Response {
        if ($receiver->isAnonymous()) {
            $this->addFlash('error', 'Cet utilisateur a supprimé son compte. Vous ne pouvez plus envoyer de messages.');
            return $this->redirectToRoute('app_message_index');
        }

        $message = new Message();
        $form = $this->createForm(MessageType::class, $message, ['include_title' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $message
                ->setSender($this->getUser())
                ->setReceiver($receiver)
                ->setIsRead(false);

            $em->persist($message);
            $em->flush();

            $this->addFlash('success', 'Message envoyé avec succès');

            try {
                $email = (new TemplatedEmail())
                    ->from(new Address(
                        $this->getParameter('app.mailer_from'),
                        $this->getParameter('app.mailer_from_name')
                    ))
                    ->to($receiver->getEmail())
                    ->subject('Nouveau message de ' . $this->getUser())
                    ->htmlTemplate('emails/message_notification.html.twig')
                    ->context([
                        'sender' => $this->getUser(),
                        'message' => $message,
                    ]);

                $mailer->send($email);
            } catch (\Exception $e) {
                $logger->error('Erreur d\'envoi d\'email: ' . $e->getMessage());
            }

            return $this->redirectToRoute('app_message_conversation', ['id' => $receiver->getId()]);
        }

        return $this->render('message/new.html.twig', [
            'form' => $form->createView(),
            'receiver' => $receiver
        ]);
    }


    #[Route('/conversation/{id}', name: 'conversation', methods: ['GET', 'POST'])]
    public function conversation(
        User $otherUser,
        Request $request,
        EntityManagerInterface $em,
        MessageRepository $messageRepository,
        MailerInterface $mailer,
        LoggerInterface $logger
    ): Response {
        $user = $this->getUser();

        $messages = $messageRepository->findConversationBetweenUsers($user, $otherUser);

        $isAnonymous = $em->getRepository(ConversationDeletion::class)->findOneBy([
            'user' => $otherUser,
            'otherUser' => $user,
        ]);

        // Déterminer le titre de la conversation
        $conversationTitle = 'Nouvelle conversation';
        if (!empty($messages)) {
            $conversationTitle = $messages[0]->getTitle();
        }

        // Créer le message avec le titre de la conversation
        $message = new Message();
        $message->setTitle($conversationTitle);

        // Créer le formulaire sans le champ titre
        $form = $this->createForm(MessageType::class, $message, ['include_title' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $message
                ->setSender($user)
                ->setReceiver($otherUser)
                ->setIsRead(false);

            $em->persist($message);
            $em->flush();

            $this->addFlash('success', 'Message envoyé avec succès');

            try {
                $email = (new TemplatedEmail())
                    ->from(new Address(
                        $this->getParameter('app.mailer_from'),
                        $this->getParameter('app.mailer_from_name')
                    ))
                    ->to($otherUser->getEmail())
                    ->subject('Nouveau message de ' . $this->getUser())
                    ->htmlTemplate('emails/message_notification.html.twig')
                    ->context([
                        'sender' => $user,
                        'message' => $message,
                    ]);

                $mailer->send($email);
            } catch (\Exception $e) {
                $logger->error('Erreur d\'envoi d\'email: ' . $e->getMessage());
            }

            return $this->redirectToRoute('app_message_conversation', ['id' => $otherUser->getId()]);
        }

        // Marquer les messages comme lus
        $messageRepository->markMessagesAsRead($user, $otherUser);

        return $this->render('message/conversation.html.twig', [
            'messages' => $messages,
            'otherUser' => $otherUser,
            'form' => $form->createView(),
            'is_anonymous' => $isAnonymous !== null
        ]);
    }

    #[Route('/conversation/{id}/delete', name: 'delete_conversation', methods: ['POST'])]
    public function deleteConversation(
        Request $request,
        User $otherUser,
        EntityManagerInterface $em,
        MessageRepository $messageRepository
    ): Response {
        // Vérification CSRF
        $csrfToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_conversation_' . $otherUser->getId(), $csrfToken)) {
            $this->addFlash('error', 'Jeton de sécurité invalide');
            return $this->redirectToRoute('app_message_index');
        }

        $user = $this->getUser();

        $existingDeletion = $em->getRepository(ConversationDeletion::class)->findOneBy([
            'user' => $user,
            'otherUser' => $otherUser
        ]);

        if ($existingDeletion) {
            // Mise à jour de la date si déjà supprimé
            $existingDeletion->setDeletedAt(new \DateTimeImmutable());
        } else {
            $deletion = new ConversationDeletion();
            $deletion->setUser($user)
                ->setOtherUser($otherUser)
                ->setDeletedAt(new \DateTimeImmutable());
            $em->persist($deletion);
        }

        $em->flush();
        $this->addFlash('success', 'Conversation masquée avec succès');

        return $this->redirectToRoute('app_message_index');
    }
}
