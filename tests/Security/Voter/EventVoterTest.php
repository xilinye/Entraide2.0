<?php

namespace App\Tests\Security\Voter;

use App\Entity\{Event, User};
use App\Security\Voter\EventVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class EventVoterTest extends TestCase
{
    public function testVoteEditAsOrganizer()
    {
        $organizer = new User();
        $event = $this->createEvent($organizer);
        $voter = new EventVoter();

        $token = $this->createToken($organizer);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $event, ['edit']));
    }

    public function testVoteDeleteAsOrganizer()
    {
        $organizer = new User();
        $event = $this->createEvent($organizer);
        $voter = new EventVoter();

        $token = $this->createToken($organizer);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $event, ['delete']));
    }

    public function testVoteEditAsAdmin()
    {
        $admin = (new User())->setRoles(['ROLE_ADMIN']);
        $event = $this->createEvent(new User()); // Different organizer
        $voter = new EventVoter();

        $token = $this->createToken($admin);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $event, ['edit']));
    }

    public function testVoteDeleteAsAdmin()
    {
        $admin = (new User())->setRoles(['ROLE_ADMIN']);
        $event = $this->createEvent(new User()); // Different organizer
        $voter = new EventVoter();

        $token = $this->createToken($admin);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $event, ['delete']));
    }

    public function testVoteEditAsRegularUser()
    {
        $user = new User();
        $event = $this->createEvent(new User()); // Different organizer
        $voter = new EventVoter();

        $token = $this->createToken($user);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, $event, ['edit']));
    }

    public function testVoteDeleteAsRegularUser()
    {
        $user = new User();
        $event = $this->createEvent(new User()); // Different organizer
        $voter = new EventVoter();

        $token = $this->createToken($user);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, $event, ['delete']));
    }

    public function testVoteEditAsAnonymous()
    {
        $event = $this->createEvent(new User());
        $voter = new EventVoter();

        $token = $this->createToken(null);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, $event, ['edit']));
    }

    public function testVoteDeleteAsAnonymous()
    {
        $event = $this->createEvent(new User());
        $voter = new EventVoter();

        $token = $this->createToken(null);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, $event, ['delete']));
    }

    public function testVoteUnsupportedAttribute()
    {
        $event = $this->createEvent(new User());
        $voter = new EventVoter();

        $token = $this->createToken(new User());

        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, $event, ['view']));
    }

    public function testVoteUnsupportedSubject()
    {
        $subject = new \stdClass();
        $voter = new EventVoter();

        $token = $this->createToken(new User());

        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, $subject, ['edit']));
    }

    private function createEvent(User $organizer): Event
    {
        $event = new Event();
        $event->setOrganizer($organizer);
        return $event;
    }

    private function createToken(?User $user): TokenInterface
    {
        /** @var TokenInterface&\PHPUnit\Framework\MockObject\MockObject $token*/
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        return $token;
    }
}
