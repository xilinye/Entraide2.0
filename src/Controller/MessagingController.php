<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MessagingController extends AbstractController
{
    #[Route('/messaging', name: 'app_messaging')]
    public function index(): Response
    {
        return $this->render('messaging/index.html.twig', [
            'controller_name' => 'MessagingController',
        ]);
    }
}
