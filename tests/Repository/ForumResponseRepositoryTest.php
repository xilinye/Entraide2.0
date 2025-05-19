<?php

namespace App\Tests\Repository;

use App\Entity\{Forum, ForumResponse, Rating, User};
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ForumResponseRepositoryTest extends KernelTestCase
{
    private EntityManager $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get('doctrine')->getManager();
        $this->entityManager->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->getConnection()->rollback();
        }
        parent::tearDown();
    }

    public function testGetTopForumResponses(): void
    {
        // Create a User
        $user = new User();
        $user->setEmail(uniqid('user') . '@example.com');
        $user->setPseudo('testuser' . uniqid());
        $user->setPassword('password');
        $this->entityManager->persist($user);

        // Create author user
        $author = new User();
        $author->setEmail(uniqid('author') . '@example.com');
        $author->setPseudo('author' . uniqid());
        $author->setPassword('password');
        $this->entityManager->persist($author);

        // Create a rater user (the one who does the ratings)
        $rater = new User();
        $rater->setEmail(uniqid('rater') . '@example.com');
        $rater->setPseudo('rater' . uniqid());
        $rater->setPassword('password');
        $this->entityManager->persist($rater);

        // Create a Forum
        $forum = new Forum();
        $forum->setTitle('Test Forum');
        $forum->setContent('Test Content');
        $forum->setAuthor($user);
        $this->entityManager->persist($forum);

        // Create responses with ratings
        $response1 = $this->createResponseWithRatings($forum, $author, $rater, 'Response 1', [5, 5]);
        $response2 = $this->createResponseWithRatings($forum, $author, $rater, 'Response 2', [4]);
        $response3 = $this->createResponseWithRatings($forum, $author, $rater, 'Response 3', [3]);
        $response4 = $this->createResponseWithRatings($forum, $author, $rater, 'Response 4', []);

        $this->entityManager->flush();
        $this->entityManager->clear();

        $repository = $this->entityManager->getRepository(ForumResponse::class);
        $topResponses = $repository->getTopForumResponses($forum, 3);

        $this->assertCount(3, $topResponses);

        $averages = array_map(fn($row) => (float)$row['average'], $topResponses);
        $contents = array_map(fn($row) => $row[0]->getContent(), $topResponses);

        $this->assertEquals([5.0, 4.0, 3.0], $averages);

        $this->assertEquals('Response 1', $contents[0]);
        $this->assertEquals('Response 2', $contents[1]);
        $this->assertEquals('Response 3', $contents[2]);
    }

    private function createResponseWithRatings(Forum $forum, User $author, User $rater, string $content, array $scores): ForumResponse
    {
        $response = new ForumResponse();
        $response->setContent($content);
        $response->setForum($forum);
        $response->setAuthor($author);
        $this->entityManager->persist($response);

        foreach ($scores as $score) {
            $rating = new Rating();
            $rating->setScore($score);
            $rating->setRater($rater);
            $rating->setRatedUser($author);
            $rating->setForumResponse($response);
            $this->entityManager->persist($rating);
            $response->addRating($rating);
        }

        return $response;
    }
}
