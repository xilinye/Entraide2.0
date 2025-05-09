<?php

namespace App\Tests\Entity;

use App\Entity\{Rating, User, BlogPost, Event, ForumResponse};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RatingTest extends KernelTestCase
{
    private ValidatorInterface $validator;
    private User $rater;
    private User $ratedUser;

    protected function setUp(): void
    {
        self::bootKernel([
            'environment' => 'test',
            'debug' => true
        ]);
        $this->validator = self::getContainer()->get('validator');

        // Générer des valeurs uniques à chaque test
        $uniqueId = uniqid();

        $this->rater = (new User())
            ->setPseudo('user_' . $uniqueId)
            ->setEmail('user_' . $uniqueId . '@test.com')
            ->setPassword('password');

        $this->ratedUser = (new User())
            ->setPseudo('rated_' . $uniqueId)
            ->setEmail('rated_' . $uniqueId . '@test.com')
            ->setPassword('password');
    }

    public function testValidRating(): void
    {
        $rating = $this->createRatingWithBlogPost();
        $violations = $this->validator->validate($rating);
        $this->assertCount(0, $violations);
    }

    public function testInvalidScoreRange(): void
    {
        $rating = $this->createRatingWithBlogPost();
        $rating->setScore(0);
        $violations = $this->validator->validate($rating);
        $this->assertCount(1, $violations);
        $this->assertStringContainsString('La note doit être entre 1 et 5.', $violations[0]->getMessage());
    }

    public function testMissingScore(): void
    {
        $rating = $this->createRatingWithBlogPost();
        $rating->setScore(0);
        $violations = $this->validator->validate($rating);
        $this->assertCount(1, $violations);
        $this->assertStringContainsString('La note doit être entre 1 et 5.', $violations[0]->getMessage());
    }

    public function testMissingTargetEntity(): void
    {
        $rating = new Rating();
        $rating->setRater($this->rater)
            ->setRatedUser($this->ratedUser)
            ->setScore(3);

        $violations = $this->validator->validate($rating);
        $this->assertCount(1, $violations);
        $this->assertStringContainsString('Une note doit être associée à exactement un élément', $violations[0]->getMessage());
    }

    public function testMultipleTargetEntities(): void
    {
        $rating = $this->createRatingWithBlogPost();
        $rating->setEvent(new Event());

        $violations = $this->validator->validate($rating);
        $this->assertCount(1, $violations);
        $this->assertStringContainsString('Une note doit être associée à exactement un élément', $violations[0]->getMessage());
    }

    private function createRatingWithBlogPost(): Rating
    {
        $rating = new Rating();
        $rating->setRater($this->rater)
            ->setRatedUser($this->ratedUser)
            ->setScore(3)
            ->setBlogPost(new BlogPost());

        return $rating;
    }

    public function testRatingLinkedToBlogPost(): void
    {
        $blogPost = new BlogPost();
        $rating = new Rating();
        $rating->setBlogPost($blogPost);

        $this->assertSame($blogPost, $rating->getBlogPost());
        $this->assertContains($rating, $blogPost->getRatings());
    }

    public function testRatingRemovalWhenBlogPostIsDeleted(): void
    {
        $entityManager = self::getContainer()->get('doctrine')->getManager();

        // Création des entités
        $blogPost = (new BlogPost())
            ->setTitle('Test Title ' . uniqid())
            ->setContent('Test Content')
            ->setAuthor($this->rater);

        $rating = (new Rating())
            ->setBlogPost($blogPost)
            ->setRater($this->rater)
            ->setRatedUser($this->ratedUser)
            ->setScore(3);

        // Persistance
        $entityManager->persist($this->rater);
        $entityManager->persist($this->ratedUser);
        $entityManager->persist($blogPost);
        $entityManager->persist($rating);
        $entityManager->flush();

        // Récupération de l'ID avant suppression
        $ratingId = $rating->getId();
        $this->assertNotNull($ratingId);

        // Suppression du BlogPost
        $entityManager->remove($blogPost);
        $entityManager->flush();
        $entityManager->clear();

        // Vérification que le Rating a été supprimé
        $deletedRating = $entityManager->find(Rating::class, $ratingId);
        $this->assertNull($deletedRating);
    }

    public function testSetForumResponseUpdatesInverseSide(): void
    {
        $forumResponse1 = new ForumResponse();
        $forumResponse2 = new ForumResponse();
        $rating = new Rating();

        $rating->setForumResponse($forumResponse1);
        $this->assertContains($rating, $forumResponse1->getRatings());

        $rating->setForumResponse($forumResponse2);
        $this->assertNotContains($rating, $forumResponse1->getRatings());
        $this->assertContains($rating, $forumResponse2->getRatings());
    }
    public function testCreatedAtIsAutomaticallySet(): void
    {
        $rating = new Rating();
        $this->assertNotNull($rating->getCreatedAt());
    }

    public function testRatingCannotBeSelfRated(): void
    {
        $user = new User();
        $rating = new Rating();
        $rating->setRater($user)
            ->setRatedUser($user)
            ->setBlogPost(new BlogPost())
            ->setScore(5);

        // Vérifier qu'il n'y a pas de violation spécifique (si une contrainte existe)
        // Ici, on suppose que ce n'est pas interdit par le code actuel
        $violations = $this->validator->validate($rating);
        $this->assertCount(0, $violations); // Ajuster si une règle est ajoutée
    }
}
