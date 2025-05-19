<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AdminControllerTest extends WebTestCase
{
    private $client;
    private $em;
    private $user;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = $this->client->getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = $this->client->getContainer()->get(UserPasswordHasherInterface::class);

        // Création de l'admin si il n'existe pas
        $this->user = $this->em->getRepository(User::class)->findOneBy(['email' => 'admin@test.com']);

        if (!$this->user) {
            $this->user = new User();
            $this->user->setEmail('admin@test.com')
                ->setPseudo('AdminTest')
                ->setPassword($passwordHasher->hashPassword($this->user, 'password'))
                ->setRoles(['ROLE_ADMIN'])
                ->setIsVerified(true);

            $this->em->persist($this->user);
            $this->em->flush();
        }

        $this->client->loginUser($this->user);
    }

    public function testDashboard(): void
    {
        $this->client->request('GET', '/admin/');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Tableau de bord Admin');
    }

    protected function tearDown(): void
    {
        // Réattacher l'entité au cas où elle serait détachée
        if ($this->em->isOpen()) {
            $user = $this->em->getRepository(User::class)->find($this->user->getId());

            if ($user) {
                $this->em->remove($user);
                $this->em->flush();
            }
        }

        parent::tearDown();

        // Détruire les références
        $this->em->clear();
        $this->em->close();
        $this->em = null;
        $this->user = null;
        $this->client = null;
    }
}
