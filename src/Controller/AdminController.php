<?php

namespace App\Controller;

use App\Entity\{User, Skill, Category};
use App\Form\{SkillFormType, CategoryFormType};
use App\Repository\{CategoryRepository, SkillRepository, UserRepository};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Knp\Component\Pager\PaginatorInterface;

#[Route('/admin', name: 'app_admin_')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
        private readonly SkillRepository $skillRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly ValidatorInterface $validator
    ) {}

    #[Route('/', name: 'dashboard')]
    public function dashboard(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'users_count' => $this->userRepository->count([]),
            'skills_count' => $this->skillRepository->count([]),
            'categories_count' => $this->categoryRepository->count([]),
            'recent_users' => $this->userRepository->findRecent(5)
        ]);
    }

    #[Route('/competences', name: 'skills', methods: ['GET', 'POST'])]
    public function manageSkills(Request $request, PaginatorInterface $paginator): Response
    {
        $skill = new Skill();
        $form = $this->createForm(SkillFormType::class, $skill);
        $form->handleRequest($request);

        // Pagination
        $query = $this->skillRepository->findAllWithCategories();
        $skills = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            15
        );

        if ($form->isSubmitted() && $form->isValid()) {
            $this->processSkillCreation($skill);
            return $this->redirectToRoute('app_admin_skills');
        }

        return $this->render('admin/skills.html.twig', [
            'form' => $form->createView(),
            'skills' => $skills,
            'categories' => $this->categoryRepository->findAllOrdered()
        ]);
    }

    #[Route('/competences/{id}/supprimer', name: 'delete_skill', methods: ['POST'])]
    public function deleteSkill(Request $request, Skill $skill): Response
    {
        if ($this->isCsrfTokenValid('delete' . $skill->getId(), $request->request->get('_token'))) {
            $this->skillRepository->remove($skill, true);
            $this->addFlash('success', 'Compétence supprimée avec succès');
        }

        return $this->redirectToRoute('app_admin_skills');
    }

    #[Route('/categories', name: 'categories', methods: ['GET', 'POST'])]
    public function manageCategories(Request $request): Response
    {
        $category = new Category();
        $form = $this->createForm(CategoryFormType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->processCategoryCreation($category);
            return $this->redirectToRoute('app_admin_categories');
        }

        return $this->render('admin/categories.html.twig', [
            'form' => $form->createView(),
            'categories' => $this->categoryRepository->findAllOrdered()
        ]);
    }

    #[Route('/categories/{id}/supprimer', name: 'delete_category', methods: ['POST'])]
    public function deleteCategory(Request $request, Category $category): Response
    {
        if ($this->isCsrfTokenValid('delete' . $category->getId(), $request->request->get('_token'))) {
            if ($category->getSkills()->count() > 0) {
                $this->addFlash('danger', 'Impossible de supprimer une catégorie avec des compétences associées');
            } else {
                $this->categoryRepository->remove($category, true);
                $this->addFlash('success', 'Catégorie supprimée avec succès');
            }
        }

        return $this->redirectToRoute('app_admin_categories');
    }

    #[Route('/utilisateurs', name: 'users')]
    public function manageUsers(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $users = $this->userRepository->paginate($page, 20);

        return $this->render('admin/users.html.twig', [
            'users' => $users,
            'recent_users' => $this->userRepository->findRecent(5)
        ]);
    }

    #[Route('/utilisateurs/{id}/supprimer', name: 'delete_user', methods: ['POST'])]
    public function deleteUser(Request $request, User $user): Response
    {
        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            if ($user === $this->getUser()) {
                $this->addFlash('danger', 'Vous ne pouvez pas supprimer votre propre compte');
            } else {
                $this->userRepository->remove($user, true);
                $this->addFlash('success', 'Utilisateur supprimé avec succès');
            }
        }

        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/utilisateurs/{id}/promouvoir', name: 'promote_user', methods: ['POST'])]
    public function promoteUser(Request $request, User $user): Response
    {
        if ($this->isCsrfTokenValid('promote' . $user->getId(), $request->request->get('_token'))) {
            $this->handleUserPromotion($user);
        }

        return $this->redirectToRoute('app_admin_users');
    }

    private function processSkillCreation(Skill $skill): void
    {
        $errors = $this->validator->validate($skill);
        if (count($errors) > 0) {
            $this->addFlash('danger', (string) $errors);
            return;
        }

        try {
            $this->em->persist($skill);
            $this->em->flush();
            $this->addFlash('success', 'Compétence créée avec succès');
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erreur : ' . $e->getMessage());
        }
    }

    private function processCategoryCreation(Category $category): void
    {
        try {
            if ($this->categoryRepository->existsByName($category->getName())) {
                throw new \RuntimeException('Le nom de catégorie existe déjà');
            }

            $this->em->persist($category);
            $this->em->flush();
            $this->addFlash('success', 'Catégorie créée avec succès');
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erreur : ' . $e->getMessage());
        }
    }

    private function handleUserPromotion(User $user): void
    {
        $roles = $user->getRoles();

        if (in_array('ROLE_ADMIN', $roles, true)) {
            if ($user === $this->getUser()) {
                $this->addFlash('warning', 'Vous ne pouvez pas retirer vos propres privilèges d\'administrateur');
            } else {
                $user->setRoles(array_diff($roles, ['ROLE_ADMIN']));
                $this->userRepository->save($user, true);
                $this->addFlash('success', 'Privilèges d\'administrateur retirés');
            }
        } else {
            $user->setRoles(array_merge($roles, ['ROLE_ADMIN']));
            $this->userRepository->save($user, true);
            $this->addFlash('success', 'Utilisateur promu en administrateur');
        }
    }
}
