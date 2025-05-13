<?php

namespace App\Tests\Entity;

use App\Entity\{
    BlogPost,
    Category,
    ConversationDeletion,
    Event,
    Forum,
    ForumResponse,
    Message,
    Rating,
    Skill,
    User
};
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testConstructor(): void
    {
        $user = new User();
        $this->assertInstanceOf(DateTimeImmutable::class, $user->getCreatedAt());
        $this->assertNotNull($user->getRegistrationToken());
        $this->assertTrue($user->getTokenExpiresAt() > $user->getCreatedAt());
        $this->assertFalse($user->isVerified());
        $this->assertEmpty($user->getSkills());
        $this->assertEmpty($user->getSentMessages());
        $this->assertEmpty($user->getReceivedMessages());
        $this->assertEmpty($user->getBlogPosts());
        $this->assertEmpty($user->getConversationDeletions());
        $this->assertEmpty($user->getForums());
        $this->assertEmpty($user->getForumResponses());
        $this->assertEmpty($user->getOrganizedEvents());
        $this->assertEmpty($user->getAttendedEvents());
        $this->assertEmpty($user->getRatingsReceived());
        $this->assertEmpty($user->getRatingsGiven());
    }

    public function testGetSetPseudo(): void
    {
        $user = new User();
        $user->setPseudo('john_doe');
        $this->assertEquals('john_doe', $user->getPseudo());
    }

    public function testGetSetEmail(): void
    {
        $user = new User();
        $user->setEmail('john@example.com');
        $this->assertEquals('john@example.com', $user->getEmail());
    }

    public function testGetSetPassword(): void
    {
        $user = new User();
        $user->setPassword('secure123');
        $this->assertEquals('secure123', $user->getPassword());
    }

    public function testGetSetIsVerified(): void
    {
        $user = new User();
        $user->setIsVerified(true);
        $this->assertTrue($user->isVerified());
    }

    public function testGetSetRegistrationToken(): void
    {
        $user = new User();
        $user->setRegistrationToken('token123');
        $this->assertEquals('token123', $user->getRegistrationToken());
    }

    public function testRegistrationTokenGenerationWhenNull(): void
    {
        $user = new User();
        $user->setRegistrationToken(null);
        $this->assertNotNull($user->getRegistrationToken());
    }

    public function testGetSetTokenExpiresAt(): void
    {
        $user = new User();
        $expiry = new DateTimeImmutable('+1 day');
        $user->setTokenExpiresAt($expiry);
        $this->assertEquals($expiry, $user->getTokenExpiresAt());
    }

    public function testIsTokenExpired(): void
    {
        $user = new User();
        $user->setTokenExpiresAt(new DateTimeImmutable('-1 hour'));
        $this->assertTrue($user->isTokenExpired());

        $user->setTokenExpiresAt(new DateTimeImmutable('+1 hour'));
        $this->assertFalse($user->isTokenExpired());
    }

    public function testGetSetResetToken(): void
    {
        $user = new User();
        $user->setResetToken('reset123');
        $this->assertEquals('reset123', $user->getResetToken());
    }

    public function testGetSetResetTokenExpiresAt(): void
    {
        $user = new User();
        $expiry = new DateTimeImmutable('+1 day');
        $user->setResetTokenExpiresAt($expiry);
        $this->assertEquals($expiry, $user->getResetTokenExpiresAt());
    }

    public function testIsResetTokenExpired(): void
    {
        $user = new User();
        $user->setResetTokenExpiresAt(new DateTimeImmutable('-1 hour'));
        $this->assertTrue($user->isResetTokenExpired());

        $user->setResetTokenExpiresAt(new DateTimeImmutable('+1 hour'));
        $this->assertFalse($user->isResetTokenExpired());
    }

    public function testGetSetProfileImage(): void
    {
        $user = new User();
        $user->setProfileImage('profile.jpg');
        $this->assertEquals('profile.jpg', $user->getProfileImage());
    }

    public function testGetSetDeletedAt(): void
    {
        $user = new User();
        $deletedAt = new DateTimeImmutable();
        $user->setDeletedAt($deletedAt);
        $this->assertEquals($deletedAt, $user->getDeletedAt());
        $this->assertTrue($user->isDeleted());
    }

    public function testRoles(): void
    {
        $user = new User();
        $this->assertContains('ROLE_USER', $user->getRoles());

        $user->setRoles(['ROLE_ADMIN']);
        $roles = $user->getRoles();
        $this->assertContains('ROLE_ADMIN', $roles);
        $this->assertContains('ROLE_USER', $roles);
        $this->assertCount(2, $roles);
    }

    public function testIsAdmin(): void
    {
        $user = new User();
        $this->assertFalse($user->isAdmin());

        $user->setRoles(['ROLE_ADMIN']);
        $this->assertTrue($user->isAdmin());
    }

    public function testIsAnonymous(): void
    {
        $user = new User();
        $this->assertFalse($user->isAnonymous());

        $user->setRoles(['ROLE_ANONYMOUS']);
        $this->assertTrue($user->isAnonymous());
    }

    public function testAddRemoveSkill(): void
    {
        $user = new User();
        $skill = new Skill();

        $user->addSkill($skill);
        $this->assertTrue($user->getSkills()->contains($skill));
        $this->assertTrue($skill->getUsers()->contains($user));

        $user->removeSkill($skill);
        $this->assertFalse($user->getSkills()->contains($skill));
        $this->assertFalse($skill->getUsers()->contains($user));
    }

    public function testGetSkillsByCategory(): void
    {
        $user = new User();
        $category1 = new Category();
        $category1->setName('Category 1');
        $category2 = new Category();
        $category2->setName('Category 2');

        $skill1 = new Skill();
        $skill1->setCategory($category1);
        $skill2 = new Skill();
        $skill2->setCategory($category2);
        $skill3 = new Skill();
        $skill3->setCategory($category1);

        $user->addSkill($skill1);
        $user->addSkill($skill2);
        $user->addSkill($skill3);

        $grouped = $user->getSkillsByCategory();
        $this->assertArrayHasKey('Category 1', $grouped);
        $this->assertArrayHasKey('Category 2', $grouped);
        $this->assertCount(2, $grouped['Category 1']);
        $this->assertCount(1, $grouped['Category 2']);
    }

    public function testAddRemoveSentMessage(): void
    {
        $user = new User();
        $message = new Message();

        $user->addSentMessage($message);
        $this->assertTrue($user->getSentMessages()->contains($message));
        $this->assertSame($user, $message->getSender());

        $user->removeSentMessage($message);
        $this->assertFalse($user->getSentMessages()->contains($message));
        $this->assertNull($message->getSender());
    }

    public function testAddRemoveReceivedMessage(): void
    {
        $user = new User();
        $message = new Message();

        $user->addReceivedMessage($message);
        $this->assertTrue($user->getReceivedMessages()->contains($message));
        $this->assertSame($user, $message->getReceiver());

        $user->removeReceivedMessage($message);
        $this->assertFalse($user->getReceivedMessages()->contains($message));
        $this->assertNull($message->getReceiver());
    }

    public function testAddRemoveBlogPost(): void
    {
        $user = new User();
        $blogPost = new BlogPost();

        $user->addBlogPost($blogPost);
        $this->assertTrue($user->getBlogPosts()->contains($blogPost));
        $this->assertSame($user, $blogPost->getAuthor());

        $user->removeBlogPost($blogPost);
        $this->assertFalse($user->getBlogPosts()->contains($blogPost));
        $this->assertNull($blogPost->getAuthor());
    }

    public function testAddRemoveConversationDeletion(): void
    {
        $user = new User();
        $conversationDeletion = new ConversationDeletion();

        $user->addConversationDeletion($conversationDeletion);
        $this->assertTrue($user->getConversationDeletions()->contains($conversationDeletion));
        $this->assertSame($user, $conversationDeletion->getUser());

        $user->removeConversationDeletion($conversationDeletion);
        $this->assertFalse($user->getConversationDeletions()->contains($conversationDeletion));
        $this->assertNull($conversationDeletion->getUser());
    }

    public function testAddRemoveForum(): void
    {
        $user = new User();
        $forum = new Forum();

        $user->addForum($forum);
        $this->assertTrue($user->getForums()->contains($forum));
        $this->assertSame($user, $forum->getAuthor());

        $user->removeForum($forum);
        $this->assertFalse($user->getForums()->contains($forum));
        $this->assertNull($forum->getAuthor());
    }

    public function testAddRemoveForumResponse(): void
    {
        $user = new User();
        $response = new ForumResponse();

        $user->addForumResponse($response);
        $this->assertTrue($user->getForumResponses()->contains($response));
        $this->assertSame($user, $response->getAuthor());

        $user->removeForumResponse($response);
        $this->assertFalse($user->getForumResponses()->contains($response));
        $this->assertNull($response->getAuthor());
    }

    public function testAddRemoveOrganizedEvent(): void
    {
        $user = new User();
        $event = new Event();

        $user->addOrganizedEvent($event);
        $this->assertTrue($user->getOrganizedEvents()->contains($event));
        $this->assertSame($user, $event->getOrganizer());

        $user->removeOrganizedEvent($event);
        $this->assertFalse($user->getOrganizedEvents()->contains($event));
        $this->assertNull($event->getOrganizer());
    }

    public function testAddRemoveAttendedEvent(): void
    {
        $user = new User();
        $event = new Event();

        $user->addAttendedEvent($event);
        $this->assertTrue($user->getAttendedEvents()->contains($event));
        $this->assertTrue($event->getAttendees()->contains($user));

        $user->removeAttendedEvent($event);
        $this->assertFalse($user->getAttendedEvents()->contains($event));
        $this->assertFalse($event->getAttendees()->contains($user));
    }

    public function testAddRemoveRatingReceived(): void
    {
        $user = new User();
        $rating = new Rating();
        $rating->setRatedUser($user);

        $user->getRatingsReceived()->add($rating);
        $this->assertTrue($user->getRatingsReceived()->contains($rating));

        $user->getRatingsReceived()->removeElement($rating);
        $this->assertFalse($user->getRatingsReceived()->contains($rating));
    }

    public function testAddRemoveRatingGiven(): void
    {
        $user = new User();
        $rating = new Rating();
        $rating->setRater($user);

        $user->getRatingsGiven()->add($rating);
        $this->assertTrue($user->getRatingsGiven()->contains($rating));

        $user->getRatingsGiven()->removeElement($rating);
        $this->assertFalse($user->getRatingsGiven()->contains($rating));
    }

    public function testGetAverageRating(): void
    {
        $user = new User();
        $this->assertEquals(0.0, $user->getAverageRating());

        $rating1 = new Rating();
        $rating1->setScore(4);
        $rating1->setRatedUser($user);
        $user->getRatingsReceived()->add($rating1);

        $rating2 = new Rating();
        $rating2->setScore(2);
        $rating2->setRatedUser($user);
        $user->getRatingsReceived()->add($rating2);

        $this->assertEquals(3.0, $user->getAverageRating());
    }

    public function testGetRatingDetails(): void
    {
        $user = new User();

        $blogPost = new BlogPost();
        $event = new Event();
        $forumResponse = new ForumResponse();

        $rating1 = new Rating();
        $rating1->setScore(4)->setBlogPost($blogPost);
        $user->getRatingsReceived()->add($rating1);

        $rating2 = new Rating();
        $rating2->setScore(2)->setEvent($event);
        $user->getRatingsReceived()->add($rating2);

        $rating3 = new Rating();
        $rating3->setScore(5)->setForumResponse($forumResponse);
        $user->getRatingsReceived()->add($rating3);

        $details = $user->getRatingDetails();

        $this->assertEquals(4.0, $details['blog']['average']);
        $this->assertEquals(2.0, $details['event']['average']);
        $this->assertEquals(5.0, $details['forum']['average']);
        $this->assertEquals(1, $details['blog']['total']);
        $this->assertEquals(1, $details['event']['total']);
        $this->assertEquals(1, $details['forum']['total']);
    }

    public function testToString(): void
    {
        $user = new User();
        $user->setPseudo('john_doe');
        $this->assertEquals('john_doe', (string)$user);

        $userSansPseudo = new User();
        $this->assertEquals('Nouvel utilisateur', (string)$userSansPseudo);
    }

    public function testUserInterfaceMethods(): void
    {
        $user = new User();
        $user->setPseudo('user123');
        $user->setPassword('pass123');

        $this->assertEquals('user123', $user->getUserIdentifier());
        $this->assertEquals('pass123', $user->getPassword());
        $user->eraseCredentials();
        $this->assertTrue(true);
    }

    public function testHasRoleCaseInsensitive(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN']);
        $this->assertTrue($user->hasRole('role_admin'));
        $this->assertTrue($user->hasRole('ROLE_ADMIN'));
    }

    public function testAddDuplicateSkill(): void
    {
        $user = new User();
        $skill = new Skill();
        $user->addSkill($skill);
        $user->addSkill($skill); // Doublon
        $this->assertCount(1, $user->getSkills());
    }

    public function testSetProfileImageToNull(): void
    {
        $user = new User();
        $user->setProfileImage(null);
        $this->assertNull($user->getProfileImage());
    }

    public function testRatingDetailsWithNoRatings(): void
    {
        $user = new User();
        $details = $user->getRatingDetails();
        $this->assertEquals(0, $details['blog']['total']);
        $this->assertEquals(0, $details['event']['total']);
        $this->assertEquals(0, $details['forum']['total']);
    }

    public function testRemoveNonExistentSkill(): void
    {
        $user = new User();
        $skill = new Skill();
        $user->removeSkill($skill); // Aucun effet
        $this->assertCount(0, $user->getSkills());
    }
}
