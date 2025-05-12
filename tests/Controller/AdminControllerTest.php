<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Entity\{User, Skill, Category};
use Doctrine\ORM\EntityManagerInterface;

class AdminControllerTest extends WebTestCase
{
    private $client;
    private $entityManager;
    private $adminUser;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $uniqueId = uniqid();
        $this->adminUser = (new User())
            ->setPseudo('admin_test_' . $uniqueId)
            ->setEmail('admin_' . $uniqueId . '@test.com')
            ->setPassword('password')
            ->setRoles(['ROLE_ADMIN']);

        $this->entityManager->persist($this->adminUser);
        $this->entityManager->flush();

        $this->client->loginUser($this->adminUser);
    }

    public function testDeleteSkill(): void
    {
        // Création des entités
        $category = (new Category())->setName('Test_Category_' . uniqid());
        $this->entityManager->persist($category);

        $skill = (new Skill())
            ->setName('Test_skill_' . uniqid())
            ->setCategory($category);

        $this->entityManager->persist($skill);
        $this->entityManager->flush();

        // Récupération via l'interface utilisateur
        $crawler = $this->client->request('GET', '/admin/competences');
        $form = $crawler->filter("form[action='/admin/competences/{$skill->getId()}/supprimer']")->form();

        // Soumission du formulaire
        $this->client->submit($form);

        $this->assertResponseRedirects('/admin/competences');

        // Vérification de la suppression
        $deletedSkill = $this->entityManager->getRepository(Skill::class)->find($skill->getId());
        $this->assertNull($deletedSkill);
    }

    protected function tearDown(): void
    {
        $this->entityManager->createQuery('DELETE FROM App\Entity\Skill')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Category')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\User')->execute();
        parent::tearDown();
    }
}
