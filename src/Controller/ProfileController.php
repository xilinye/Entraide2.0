<?php

namespace App\Controller;

use App\Entity\{Skill, User};
use App\Form\SkillSelectionType;
use App\Service\{SkillManager, UserManager};
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\SecurityBundle\Security;

#[Route('/profile', name: 'app_profile_')]
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    public function __construct(
        private readonly SkillManager $skillManager,
        private readonly UserManager $userManager
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

        // Récupération correcte depuis les données du formulaire
        $form = $this->createForm(SkillSelectionType::class);
        $form->handleRequest($request);

        // Récupérer la catégorie après la soumission
        $selectedCategory = $form->get('category')->getData();

        // Recréer le formulaire avec la catégorie sélectionnée
        $form = $this->createForm(SkillSelectionType::class, null, [
            'selected_category' => $selectedCategory
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $skill = $form->get('skill')->getData();

            if (!$skill instanceof Skill) {
                $this->addFlash('danger', 'Veuillez sélectionner une compétence valide');
                return $this->redirectToRoute('app_profile_skills');
            }

            try {
                $this->skillManager->handleSkillSubmission(
                    $user,
                    $skill
                );
                $this->addFlash('success', 'Compétence ajoutée avec succès');
            } catch (\RuntimeException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
            return $this->redirectToRoute('app_profile_skills');
        }

        return $this->render('profile/skills.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
            'skillsByCategory' => $user->getSkillsByCategory(),
        ]);
    }

    #[Route('/competences/{id}/supprimer', name: 'skill_remove', methods: ['POST'])]
    public function removeSkill(Request $request, Skill $skill): Response
    {
        $user = $this->getAuthenticatedUser();

        if (!$this->isCsrfTokenValid('delete' . $skill->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide');
            return $this->redirectToRoute('app_profile_skills');
        }

        try {
            $this->skillManager->removeUserSkill($user, $skill);
            $this->addFlash('success', 'Compétence supprimée avec succès');
        } catch (\RuntimeException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_profile_skills');
    }

    #[Route('/supprimer', name: 'delete', methods: ['POST'])]
    public function deleteAccount(
        Request $request,
        Security $security
    ): Response {
        $user = $this->getAuthenticatedUser();

        if (!$this->isCsrfTokenValid('delete_account', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide');
            return $this->redirectToRoute('app_profile_index');
        }

        try {
            $this->userManager->deleteUser($user);

            $response = $security->logout(false);
            $request->getSession()->invalidate();

            $this->addFlash('success', 'Compte supprimé avec succès');
            return $this->redirectToRoute('app_page_home');
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erreur lors de la suppression : ' . $e->getMessage());
            return $this->redirectToRoute('app_profile_index');
        }
    }

    private function getAuthenticatedUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        return $user;
    }
}
