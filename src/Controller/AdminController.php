<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Skill;
use App\Form\SkillFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin', name: 'app_admin_')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('/', name: 'dashboard')]
    public function dashboard(EntityManagerInterface $em): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'users' => $em->getRepository(User::class)->findAll(),
            'skills' => $em->getRepository(Skill::class)->findAll()
        ]);
    }

    #[Route('/competences', name: 'skills')]
    public function manageSkills(Request $request, EntityManagerInterface $em): Response
    {
        $skill = new Skill();
        $form = $this->createForm(SkillFormType::class, $skill);
        
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($skill);
            $em->flush();
            $this->addFlash('success', 'Compétence créée avec succès');
            return $this->redirectToRoute('app_admin_skills');
        }

        return $this->render('admin/skills.html.twig', [
            'form' => $form,
            'skills' => $em->getRepository(Skill::class)->findAll()
        ]);
    }

    #[Route('/competences/{id}/supprimer', name: 'delete_skill')]
    public function deleteSkill(Skill $skill, EntityManagerInterface $em): Response
    {
        $em->remove($skill);
        $em->flush();
        $this->addFlash('success', 'Compétence supprimée');
        return $this->redirectToRoute('app_admin_skills');
    }

    #[Route('/utilisateurs', name: 'users')]
    public function manageUsers(EntityManagerInterface $em): Response
    {
        return $this->render('admin/users.html.twig', [
            'users' => $em->getRepository(User::class)->findAll()
        ]);
    }

    #[Route('/utilisateurs/{id}/supprimer', name: 'delete_user')]
    public function deleteUser(User $user, EntityManagerInterface $em): Response
    {
        $em->remove($user);
        $em->flush();
        $this->addFlash('success', 'Utilisateur supprimé');
        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/utilisateurs/{id}/promouvoir', name: 'promote_user')]
    public function promoteUser(User $user, EntityManagerInterface $em): Response
    {
        $roles = $user->getRoles();
    
        if (!in_array('ROLE_ADMIN', $roles, true)) {
            $roles[] = 'ROLE_ADMIN';
            $user->setRoles(array_unique($roles));
            $em->flush();
            $this->addFlash('success', $user->getPseudo().' est maintenant administrateur');
        } else {
            $this->addFlash('warning', 'Cet utilisateur est déjà administrateur');
        }
    
        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/utilisateurs', name: 'app_admin_users')]
    public function users(EntityManagerInterface $em): Response
    {
        return $this->render('admin/users.html.twig', [
            'users' => $em->getRepository(User::class)->findAll(),
            'recentUsers' => $em->getRepository(User::class)->findBy(
                [], 
                ['createdAt' => 'DESC'], 
                10
            )
        ]);
    }
}