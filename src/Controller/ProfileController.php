<?php

namespace App\Controller;

use App\Entity\Skill;
use App\Entity\User;
use App\Form\SkillSelectionType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile', name: 'app_profile_')]
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {}

    #[Route('/', name: 'index')]
    public function index(): Response
    {
        return $this->render('profile/index.html.twig', [
            'user' => $this->getAuthenticatedUser(),
        ]);
    }

    #[Route('/competences', name: 'skills', methods: ['GET', 'POST'])]
    public function manageSkills(Request $request): Response
    {
        $user = $this->getAuthenticatedUser();
        $form = $this->createForm(SkillSelectionType::class, null, [
            'selected_category' => null,
            'required' => false
        ]);

        $form->handleRequest($request);

        // Check if the form was submitted and contains category data
        if ($form->isSubmitted() && $form->has('category')) {
            $selectedCategory = $form->get('category')->getData();
            // Recreate the form with the selected category
            $form = $this->createForm(SkillSelectionType::class, null, [
                'selected_category' => $selectedCategory,
                'required' => true
            ]);
            // Handle the request again with the new form
            $form->handleRequest($request);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            return $this->handleFormSubmission($form->get('skill')->getData());
        }

        return $this->renderForm($user, $form);
    }

    #[Route('/competences/{id}/supprimer', name: 'skill_remove', methods: ['POST'])]
    public function removeSkill(Request $request, Skill $skill): Response
    {
        if (!$this->isCsrfTokenValid('delete' . $skill->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_profile_skills');
        }

        $user = $this->getAuthenticatedUser();
        $user->removeSkill($skill);
        $this->em->flush();

        $this->addFlash('success', 'Compétence supprimée avec succès');
        return $this->redirectToRoute('app_profile_skills');
    }

    private function handleFormSubmission($skill): Response
    {
        if (!$skill instanceof Skill) {
            $this->addFlash('danger', 'Veuillez sélectionner une compétence valide.');
            return $this->redirectToRoute('app_profile_skills');
        }

        $user = $this->getAuthenticatedUser();
        $this->processSkillAddition($user, $skill);

        return $this->redirectToRoute('app_profile_skills');
    }

    private function renderForm(User $user, $form): Response
    {
        return $this->render('profile/skills.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
            'skillsByCategory' => $user->getSkillsByCategory(),
        ]);
    }

    private function getAuthenticatedUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Accès refusé : utilisateur non authentifié');
        }
        return $user;
    }

    private function processSkillAddition(User $user, Skill $skill): void
    {
        if ($user->hasSkill($skill)) {
            $this->addFlash('warning', 'Vous possédez déjà cette compétence');
            return;
        }

        if (!$skill->getCategory()) {
            $this->addFlash('danger', 'Compétence non classée');
            return;
        }

        try {
            $user->addSkill($skill);
            $this->em->flush();
            $this->addFlash('success', 'Compétence ajoutée avec succès');
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erreur lors de l\'ajout : ' . $e->getMessage());
        }
    }
}
