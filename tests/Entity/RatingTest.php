<?php

namespace App\Tests\Entity;

use App\Entity\BlogPost;
use App\Entity\Event;
use App\Entity\ForumResponse;
use App\Entity\Rating;
use App\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RatingTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testValidRating(): void
    {
        $rater = new User();
        $ratedUser = new User();
        $blogPost = new BlogPost();

        $rating = new Rating();
        $rating->setRater($rater);
        $rating->setRatedUser($ratedUser);
        $rating->setBlogPost($blogPost);
        $rating->setScore(3);

        $violations = $this->validator->validate($rating);
        $this->assertCount(0, $violations);
    }

    public function testInvalidTargets(): void
    {
        $rater = new User();
        $ratedUser = new User();
        $blogPost = new BlogPost();
        $event = new Event();

        $rating = new Rating();
        $rating->setRater($rater);
        $rating->setRatedUser($ratedUser);
        $rating->setBlogPost($blogPost);
        $rating->setEvent($event);
        $rating->setScore(3);

        $violations = $this->validator->validate($rating);
        $this->assertCount(1, $violations);
        $this->assertEquals('Une note doit être associée à exactement un élément (article, événement ou réponse).', $violations[0]->getMessage());
    }

    public function testScoreValidation(): void
    {
        $rater = new User();
        $ratedUser = new User();
        $blogPost = new BlogPost();

        // Test score 0 (invalid)
        $rating = new Rating();
        $rating->setRater($rater);
        $rating->setRatedUser($ratedUser);
        $rating->setBlogPost($blogPost);
        $rating->setScore(0);
        $violations = $this->validator->validate($rating);
        $this->assertCount(1, $violations);

        // Test score 6 (invalid)
        $rating->setScore(6);
        $violations = $this->validator->validate($rating);
        $this->assertCount(1, $violations);

        // Test score 1 (valid)
        $rating->setScore(1);
        $violations = $this->validator->validate($rating);
        $this->assertCount(0, $violations);

        // Test score 5 (valid)
        $rating->setScore(5);
        $violations = $this->validator->validate($rating);
        $this->assertCount(0, $violations);
    }

    public function testBlogPostAssociation(): void
    {
        /** @var BlogPost&\PHPUnit\Framework\MockObject\MockObject $oldBlogPost */
        $oldBlogPost = $this->createMock(BlogPost::class);
        /** @var BlogPost&\PHPUnit\Framework\MockObject\MockObject $newBlogPost */
        $newBlogPost = $this->createMock(BlogPost::class);

        $oldBlogPost->expects($this->once())
            ->method('removeRating')
            ->with($this->isInstanceOf(Rating::class));

        $newBlogPost->expects($this->once())
            ->method('addRating')
            ->with($this->isInstanceOf(Rating::class));

        $rating = new Rating();
        $rating->setBlogPost($oldBlogPost);
        $rating->setBlogPost($newBlogPost);
    }

    public function testEventAssociation(): void
    {
        /** @var Event&\PHPUnit\Framework\MockObject\MockObject $oldEvent */
        $oldEvent = $this->createMock(Event::class);
        /** @var Event&\PHPUnit\Framework\MockObject\MockObject $newEvent */
        $newEvent = $this->createMock(Event::class);

        $oldEvent->expects($this->once())
            ->method('removeRating')
            ->with($this->isInstanceOf(Rating::class));

        $newEvent->expects($this->once())
            ->method('addRating')
            ->with($this->isInstanceOf(Rating::class));

        $rating = new Rating();
        $rating->setEvent($oldEvent);
        $rating->setEvent($newEvent);
    }

    public function testForumResponseAssociation(): void
    {
        /** @var ForumResponse&\PHPUnit\Framework\MockObject\MockObject $oldForumResponse */
        $oldForumResponse = $this->createMock(ForumResponse::class);
        /** @var ForumResponse&\PHPUnit\Framework\MockObject\MockObject $newForumResponse */
        $newForumResponse = $this->createMock(ForumResponse::class);

        $oldForumResponse->expects($this->once())
            ->method('removeRating')
            ->with($this->isInstanceOf(Rating::class));

        $newForumResponse->expects($this->once())
            ->method('addRating')
            ->with($this->isInstanceOf(Rating::class));

        $rating = new Rating();
        $rating->setForumResponse($oldForumResponse);
        $rating->setForumResponse($newForumResponse);
    }

    public function testCreatedAt(): void
    {
        $rating = new Rating();
        $this->assertNotNull($rating->getCreatedAt());
    }

    public function testNoTargets(): void
    {
        $rating = new Rating();
        $rating->setRater(new User());
        $rating->setRatedUser(new User());
        $rating->setScore(3); // Aucune cible définie

        $violations = $this->validator->validate($rating);
        $this->assertCount(1, $violations);
    }

    public function testForumResponseAsTarget(): void
    {
        $forumResponse = new ForumResponse();
        $rating = new Rating();
        $rating->setRater(new User());
        $rating->setRatedUser(new User());
        $rating->setForumResponse($forumResponse);
        $rating->setScore(3);

        $violations = $this->validator->validate($rating);
        $this->assertCount(0, $violations);
    }

    public function testRatedUserGetterAndSetter(): void
    {
        $user = new User();
        $rating = new Rating();
        $rating->setRatedUser($user);
        $this->assertSame($user, $rating->getRatedUser());
    }
}
