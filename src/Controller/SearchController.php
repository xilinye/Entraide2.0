<?php

namespace App\Controller;

use App\Entity\{Category, Skill};
use App\Form\SearchType;
use App\Repository\{UserRepository,CategoryRepository};
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response,JsonResponse};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\ORM\EntityManagerInterface;

#[IsGranted('ROLE_USER')]
#[Route('/search', name: 'app_search_')]
class SearchController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(Request $request, UserRepository $userRepository, CategoryRepository $categoryRepository): Response
    {
        $form = $this->createForm(SearchType::class);
        $form->handleRequest($request);

        $selectedSkill = null;
        $selectedCategory = null;
        $isSubmitted = $form->isSubmitted() && $form->isValid();

        if ($isSubmitted) {
            $selectedCategory = $form->get('category')->getData();
            $selectedSkill = $form->get('skill')->getData();
        }

        // Chargement TOUJOURS des utilisateurs avec filtres
        $users = $userRepository->findByFilters(
            $selectedCategory,
            $selectedSkill,
            $this->getUser()
        );

        return $this->render('search/index.html.twig', [
            'form' => $form->createView(),
            'users' => $users,
            'isSubmitted' => $isSubmitted,
            'selectedCategory' => $selectedCategory,
            'selectedSkill' => $selectedSkill
        ]);
    }

    #[Route('/skills', name: 'skills_by_category')]
    public function getSkillsByCategory(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $categoryId = $request->query->get('categoryId');
        $skills = [];

        if ($categoryId) {
            $category = $em->getRepository(Category::class)->find($categoryId);
            if ($category) {
                $skills = $category->getSkills()->map(function (Skill $skill) {
                    return ['id' => $skill->getId(), 'name' => $skill->getName()];
                })->toArray();
            }
        } else {
            // Retourne toutes les compétences si aucune catégorie n'est sélectionnée
            $allSkills = $em->getRepository(Skill::class)->findAll();
            $skills = array_map(function (Skill $skill) {
                return ['id' => $skill->getId(), 'name' => $skill->getName()];
            }, $allSkills);
        }

        return $this->json($skills);
    }
}
