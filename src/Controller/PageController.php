<?php

namespace App\Controller;

use App\Form\ContactType;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Mime\Address;

#[Route('/', name: 'app_page_')]
class PageController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function home(): Response
    {
        return $this->render('page/home.html.twig');
    }
    #[Route('/a-propos', name: 'about')]
    public function about(): Response
    {
        return $this->render('page/about.html.twig');
    }

    #[Route('/contact', name: 'contact')]
    public function contact(Request $request, MailerInterface $mailer): Response
    {
        $form = $this->createForm(ContactType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            // Création de l'email avec le bon format
            $email = (new TemplatedEmail())
                ->from(new Address($data['email'], $data['name']))
                ->to(new Address('contact@entraide20.com', 'Support EntrAide'))
                ->subject($data['subject'])
                ->htmlTemplate('emails/contact.html.twig')
                ->context([
                    'sender_email' => $data['email'],
                    'sender_name' => $data['name'],
                    'message' => $data['message']
                ]);

            $mailer->send($email);

            $this->addFlash('success', 'Message envoyé avec succès !');
            return $this->redirectToRoute('app_page_contact');
        }

        return $this->render('page/contact.html.twig', [
            'form' => $form->createView()
        ]);
    }

    #[Route('/conditions-utilisation', name: 'terms')]
    public function terms(): Response
    {
        return $this->render('page/terms.html.twig');
    }

    #[Route('/confidentialite', name: 'privacy')]
    public function privacy(): Response
    {
        return $this->render('page/privacy.html.twig');
    }
}
