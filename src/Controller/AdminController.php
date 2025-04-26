<?php

namespace App\Controller;


use App\Entity\{User, Skill, Category};
use App\Form\{SkillFormType, CategoryFormType};
use App\Repository\{CategoryRepository, SkillRepository, UserRepository, MessageRepository, EventRepository, ForumRepository, BlogPostRepository};
use App\Service\{UserManager, SkillManager, CategoryManager};
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Bundle\SecurityBundle\Security;

#[Route('/admin', name: 'app_admin_')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
        private readonly SkillRepository $skillRepository,
        private readonly MessageRepository $messageRepository,
        private readonly EventRepository $eventRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly BlogPostRepository $blogPostRepository,
        private readonly ForumRepository $forumRepository,
        private readonly ValidatorInterface $validator,
        private readonly UserManager $userManager,
        private readonly SkillManager $skillManager,
        private readonly CategoryManager $categoryManager
    ) {}

    #[Route('/', name: 'dashboard')]
    public function dashboard(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'users_count' => $this->userRepository->countActiveUsers(),
            'skills_count' => $this->skillRepository->count([]),
            'categories_count' => $this->categoryRepository->count([]),
            'messages_count' => $this->messageRepository->count([]),
            'blog_posts_count' => $this->blogPostRepository->count([]),
            'forums_count' => $this->forumRepository->count([]),
            'events_count' => $this->eventRepository->count([]),
            'recent_users' => $this->userRepository->findRecent(5)
        ]);
    }

    #[Route('/competences', name: 'skills', methods: ['GET', 'POST'])]
    public function manageSkills(Request $request, PaginatorInterface $paginator): Response
    {
        $skill = new Skill();
        $form = $this->createForm(SkillFormType::class, $skill);
        $form->handleRequest($request);

        $query = $this->skillRepository->findAllWithCategories();
        $skills = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            15
        );

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->skillManager->createSkill($skill);
                $this->addFlash('success', 'Compétence crée avec succès');
            } catch (ValidationFailedException $e) {
                $errors = [];
                foreach ($e->getViolations() as $violation) {
                    $errors[] = $violation->getMessage();
                }
                $this->addFlash('danger', implode('<br>', $errors));
            } catch (\Exception $e) {
                $this->addFlash('danger', 'Erreur : ' . $e->getMessage());
            }
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
            try {
                $this->categoryManager->createCategory($category);
                $this->addFlash('success', 'Catégorie crée avec succès');
            } catch (\Exception $e) {
                $this->addFlash('danger', 'Erreur : ' . $e->getMessage());
            }
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
            try {
                // Sauvegarder l'ID avant suppression
                $userId = $user->getId();

                // Appel au UserManager pour gérer la suppression
                $this->userManager->deleteUser($user);

                // Vérifier si l'utilisateur existe toujours (anonymisé) ou a été complètement supprimé
                $existingUser = $this->em->getRepository(User::class)->find($userId);

                if ($existingUser) {
                    $this->addFlash('warning', 'Les contributions publiques ont été anonymisées');
                } else {
                    $this->addFlash('success', 'Utilisateur supprimé définitivement');
                }
            } catch (\Exception $e) {
                $this->addFlash('danger', 'Erreur : ' . $e->getMessage());
            }
        }
        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/utilisateurs/{id}/promouvoir', name: 'promote_user', methods: ['POST'])]
    public function promoteUser(Request $request, User $user): Response
    {
        if ($this->isCsrfTokenValid('promote' . $user->getId(), $request->request->get('_token'))) {
            try {
                $currentUser = $this->getUser();

                if ($user->isAdmin()) {
                    $this->userManager->demoteFromAdmin($user, $currentUser);
                    $this->addFlash('success', 'Privilèges administrateur retirés');
                } else {
                    $this->userManager->promoteToAdmin($user);
                    $this->addFlash('success', 'Utilisateur promu en administrateur');
                }
            } catch (RuntimeException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/utilisateurs/{id}/retrograder', name: 'demote_user', methods: ['POST'])]
    public function demoteUser(Request $request, User $user): Response
    {
        if ($this->isCsrfTokenValid('demote' . $user->getId(), $request->request->get('_token'))) {
            try {
                $this->userManager->demoteFromAdmin($user, $this->getUser());
                $this->addFlash('success', 'Privilèges administrateur retirés');
            } catch (RuntimeException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }
        return $this->redirectToRoute('app_admin_users');
    }
}
