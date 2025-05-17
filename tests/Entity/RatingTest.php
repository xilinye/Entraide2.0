<?php

namespace App\Tests\Entity;

use App\Entity\Rating;
use App\Entity\BlogPost;
use App\Entity\Event;
use App\Entity\ForumResponse;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RatingTest extends KernelTestCase
{
    private ValidatorInterface $validator;
    private Rating $rating;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->validator = self::getContainer()->get(ValidatorInterface::class);
        $this->rating = new Rating();
    }

    public function testIdIsNullInitially(): void
    {
        $this->assertNull($this->rating->getId());
    }

    public function testCreatedAtIsSetOnConstruction(): void
    {
        $this->assertNotNull($this->rating->getCreatedAt());
    }

    public function testScoreValidation(): void
    {
        // Test valid scores
        $this->rating->setScore(1);
        $this->assertSame(1, $this->rating->getScore());

        $this->rating->setScore(5);
        $this->assertSame(5, $this->rating->getScore());

        // Test invalid scores using validator
        $this->rating->setScore(0);
        $violations = $this->validator->validate($this->rating);
        $this->assertGreaterThan(0, $violations->count());

        $this->rating->setScore(6);
        $violations = $this->validator->validate($this->rating);
        $this->assertGreaterThan(0, $violations->count());
    }

    public function testComment(): void
    {
        $this->rating->setComment('Great post!');
        $this->assertSame('Great post!', $this->rating->getComment());
    }

    public function testUserAssociations(): void
    {
        $rater = new User();
        $ratedUser = new User();

        $this->rating->setRater($rater);
        $this->rating->setRatedUser($ratedUser);

        $this->assertSame($rater, $this->rating->getRater());
        $this->assertSame($ratedUser, $this->rating->getRatedUser());
    }

    public function testSingleTargetValidation(): void
    {
        // Test no target set
        $violations = $this->validator->validate($this->rating);
        $this->assertGreaterThan(0, $violations->count());

        // Test multiple targets set
        $this->rating->setBlogPost(new BlogPost());
        $this->rating->setEvent(new Event());

        $violations = $this->validator->validate($this->rating);
        $this->assertGreaterThan(0, $violations->count());
    }

    public function testBlogPostAssociation(): void
    {
        $oldBlogPost = new BlogPost();
        $newBlogPost = new BlogPost();

        $this->rating->setBlogPost($oldBlogPost);
        $this->assertSame($oldBlogPost, $this->rating->getBlogPost());
        $this->assertContains($this->rating, $oldBlogPost->getRatings());

        $this->rating->setBlogPost($newBlogPost);
        $this->assertSame($newBlogPost, $this->rating->getBlogPost());
        $this->assertContains($this->rating, $newBlogPost->getRatings());
        $this->assertNotContains($this->rating, $oldBlogPost->getRatings());
    }

    public function testEventAssociation(): void
    {
        $event = new Event();
        $this->rating->setEvent($event);

        $this->assertSame($event, $this->rating->getEvent());
        $this->assertContains($this->rating, $event->getRatings());
    }

    public function testForumResponseAssociation(): void
    {
        $oldResponse = new ForumResponse();
        $newResponse = new ForumResponse();

        $this->rating->setForumResponse($oldResponse);
        $this->assertSame($oldResponse, $this->rating->getForumResponse());
        $this->assertContains($this->rating, $oldResponse->getRatings());

        $this->rating->setForumResponse($newResponse);
        $this->assertSame($newResponse, $this->rating->getForumResponse());
        $this->assertContains($this->rating, $newResponse->getRatings());
        $this->assertNotContains($this->rating, $oldResponse->getRatings());
    }

    // Test for potential bug in Event association (missing removal from old event)
    public function testEventAssociationDoesNotRemoveOldEvent(): void
    {
        $oldEvent = new Event();
        $newEvent = new Event();

        $this->rating->setEvent($oldEvent);
        $this->rating->setEvent($newEvent);

        $this->assertCount(1, $oldEvent->getRatings()); // This will fail because of the bug
        $this->assertCount(1, $newEvent->getRatings());
    }
}
