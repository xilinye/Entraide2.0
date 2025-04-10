<?php

namespace App\Service;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;

class CategoryManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CategoryRepository $categoryRepository
    ) {}

    public function createCategory(Category $category): void
    {
        if ($this->categoryRepository->existsByName($category->getName())) {
            throw new \RuntimeException('Le nom de catégorie existe déjà');
        }

        $this->em->persist($category);
        $this->em->flush();
    }
}
