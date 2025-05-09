<?php

namespace App\Tests\Service;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use App\Service\CategoryManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class CategoryManagerTest extends TestCase
{
    public function testCreationCategorieEchoueSiNomExisteDeja(): void
    {
        // 1. Préparation du test
        $categorie = (new Category())->setName('Existant');

        // 2. Création des mocks
        $repoMock = $this->createMock(CategoryRepository::class);
        $repoMock->expects($this->once()) // Vérifie qu'on appelle existsByName une fois
            ->method('existsByName')
            ->with('Existant') // Vérifie le paramètre passé
            ->willReturn(true);

        $emMock = $this->createMock(EntityManagerInterface::class);

        // 3. Exécution + Vérification de l'exception
        $manager = new CategoryManager($emMock, $repoMock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Le nom de catégorie existe déjà');

        $manager->createCategory($categorie);
    }

    public function testCreationCategorieReussieSiNomUnique(): void
    {
        // 1. Préparation
        $categorie = (new Category())->setName('Nouveau');

        // 2. Configuration des mocks
        $repoMock = $this->createMock(CategoryRepository::class);
        $repoMock->expects($this->once())
            ->method('existsByName')
            ->with('Nouveau')
            ->willReturn(false);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once()) // Vérifie que persist est appelé
            ->method('persist')
            ->with($categorie); // Avec la bonne catégorie

        $emMock->expects($this->once()) // Vérifie que flush est appelé
            ->method('flush');

        // 3. Exécution
        $manager = new CategoryManager($emMock, $repoMock);
        $manager->createCategory($categorie);

        // Pas d'exception = test réussi
    }
}
