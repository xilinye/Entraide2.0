<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/messages', name: 'app_message_')]
#[IsGranted('ROLE_USER')]
class MessageController extends AbstractController
{
    #[Route('/envoyer/{userId}', name: 'new')]
    public function newMessage(): Response
    {
        return $this->render('message/index.html.twig', [
            'controller_name' => 'MessageController',
        ]);
    }
    #[Route('/conversation/{userId}', name: 'conversatin')]
    public function viewConversation(): Response
    {
        return $this->render('message/index.html.twig', [
            'controller_name' => 'MessageController',
        ]);
    }
}
