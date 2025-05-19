<?php

namespace App\Tests\Repository;

use App\Entity\BlogPost;
use App\Entity\Event;
use App\Entity\Forum;
use App\Entity\ForumResponse;
use App\Entity\Rating;
use App\Entity\User;
use App\Repository\RatingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Doctrine\ORM\Tools\SchemaTool;

class RatingRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private RatingRepository $ratingRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get('doctrine')->getManager();

        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->updateSchema($this->entityManager->getMetadataFactory()->getAllMetadata());

        $this->entityManager->beginTransaction();

        $this->ratingRepository = $this->entityManager->getRepository(Rating::class);
    }
    protected function tearDown(): void
    {
        // Rollback the transaction to clean up
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->rollback();
        }
        parent::tearDown();
    }
    public function testGetAverageForBlogPost(): void
    {
        $user = $this->createUser();
        $blogPost = $this->createBlogPost($user);

        $this->createRating($user, $user, $blogPost, null, null, 4);
        $this->createRating($user, $user, $blogPost, null, null, 5);
        $this->entityManager->flush();

        $this->assertEquals(4.5, $this->ratingRepository->getAverageForBlogPost($blogPost));

        $result = $this->ratingRepository->getAverageForTarget($blogPost);
        $this->assertEquals(4.5, $result['average']);
        $this->assertEquals(2, $result['total']);
    }

    public function testGetAverageForEvent(): void
    {
        $user = $this->createUser();
        $event = $this->createEvent($user);

        $this->createRating($user, $user, null, $event, null, 3);
        $this->createRating($user, $user, null, $event, null, 5);
        $this->entityManager->flush();

        $this->assertEquals(4.0, $this->ratingRepository->getAverageForEvent($event));

        $result = $this->ratingRepository->getAverageForTarget($event);
        $this->assertEquals(4.0, $result['average']);
        $this->assertEquals(2, $result['total']);
    }

    public function testGetAverageForForumResponse(): void
    {
        $user = $this->createUser();
        $forum = $this->createForum($user);
        $forumResponse = $this->createForumResponse($user, $forum);

        $this->createRating($user, $user, null, null, $forumResponse, 2);
        $this->createRating($user, $user, null, null, $forumResponse, 4);
        $this->entityManager->flush();

        $this->assertEquals(3.0, $this->ratingRepository->getAverageForForumResponse($forumResponse));

        $result = $this->ratingRepository->getAverageForTarget($forumResponse);
        $this->assertEquals(3.0, $result['average']);
        $this->assertEquals(2, $result['total']);
    }

    public function testNoRatings(): void
    {
        $user = $this->createUser();
        $blogPost = $this->createBlogPost($user);
        $this->entityManager->flush();

        $this->assertEquals(0.0, $this->ratingRepository->getAverageForBlogPost($blogPost));

        $result = $this->ratingRepository->getAverageForTarget($blogPost);
        $this->assertNull($result['average']);
        $this->assertEquals(0, $result['total']);
    }
    private function createUser(): User
    {
        $user = new User();
        $user->setEmail(uniqid('test') . '@example.com');
        $user->setPassword('password');
        $user->setPseudo(uniqid('testuser'));
        $this->entityManager->persist($user);
        return $user;
    }

    private function createBlogPost(User $author): BlogPost
    {
        $blogPost = new BlogPost();
        $blogPost->setTitle('Test Blog Post ' . uniqid());
        $blogPost->setContent('Content');
        $blogPost->setAuthor($author);
        $blogPost->setSlug('test-blog-post-' . uniqid());
        $this->entityManager->persist($blogPost);
        return $blogPost;
    }

    private function createEvent(User $organizer): Event
    {
        $event = new Event();
        $event->setTitle('Test Event');
        $event->setDescription('Description');
        $event->setStartDate(new \DateTime('tomorrow'));
        $event->setEndDate(new \DateTime('tomorrow +1 hour'));
        $event->setLocation('Location');
        $event->setMaxAttendees(10);
        $event->setOrganizer($organizer);
        $this->entityManager->persist($event);
        return $event;
    }

    private function createForum(User $author): Forum
    {
        $forum = new Forum();
        $forum->setTitle('Test Forum');
        $forum->setContent('Test Description');
        $forum->setAuthor($author);
        $this->entityManager->persist($forum);
        return $forum;
    }

    private function createForumResponse(User $author, Forum $forum): ForumResponse
    {
        $forumResponse = new ForumResponse();
        $forumResponse->setContent('Test Response');
        $forumResponse->setAuthor($author);
        $forumResponse->setForum($forum);
        $this->entityManager->persist($forumResponse);
        return $forumResponse;
    }

    private function createRating(
        User $rater,
        User $ratedUser,
        ?BlogPost $blogPost,
        ?Event $event,
        ?ForumResponse $forumResponse,
        int $score
    ): Rating {
        $rating = new Rating();
        $rating->setRater($rater);
        $rating->setRatedUser($ratedUser);
        $rating->setScore($score);

        if ($blogPost) {
            $rating->setBlogPost($blogPost);
        } elseif ($event) {
            $rating->setEvent($event);
        } elseif ($forumResponse) {
            $rating->setForumResponse($forumResponse);
        }

        $this->entityManager->persist($rating);
        return $rating;
    }
}
