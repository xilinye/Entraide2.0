<?php

namespace App\Tests\Security\Voter;

use App\Entity\BlogPost;
use App\Entity\User;
use App\Security\Voter\BlogPostVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class BlogPostVoterTest extends TestCase
{
    private BlogPostVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new BlogPostVoter();
    }

    // Tests pour la prise en charge des attributs et sujets (supports)

    public function testSupportsWithEditAndBlogPost(): void
    {
        /** @var TokenInterface&\PHPUnit\Framework\MockObject\MockObject $token*/
        $token = $this->createMock(TokenInterface::class);
        $blogPost = new BlogPost();
        $result = $this->voter->vote($token, $blogPost, [BlogPostVoter::EDIT]);
        $this->assertNotEquals(VoterInterface::ACCESS_ABSTAIN, $result, 'Le Voter ne devrait pas s\'abstenir');
    }

    public function testSupportsWithInvalidAttribute(): void
    {
        /** @var TokenInterface&\PHPUnit\Framework\MockObject\MockObject $token*/
        $token = $this->createMock(TokenInterface::class);
        $blogPost = new BlogPost();
        $result = $this->voter->vote($token, $blogPost, ['INVALID']);
        $this->assertEquals(VoterInterface::ACCESS_ABSTAIN, $result, 'Le Voter devrait s\'abstenir');
    }

    public function testSupportsWithInvalidSubject(): void
    {
        /** @var TokenInterface&\PHPUnit\Framework\MockObject\MockObject $token*/
        $token = $this->createMock(TokenInterface::class);
        $subject = new \stdClass();
        $result = $this->voter->vote($token, $subject, [BlogPostVoter::DELETE]);
        $this->assertEquals(VoterInterface::ACCESS_ABSTAIN, $result, 'Le Voter devrait s\'abstenir');
    }

    // Tests pour voteOnAttribute via la méthode vote()

    public function testAccessGrantedWhenUserIsAuthor(): void
    {
        /** @var User&\PHPUnit\Framework\MockObject\MockObject $user*/
        $user = $this->createMock(User::class);
        $blogPost = new BlogPost();
        $blogPost->setAuthor($user);

        $token = $this->createTokenMock($user);

        $result = $this->voter->vote($token, $blogPost, [BlogPostVoter::EDIT]);
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result, 'L\'auteur devrait avoir accès');
    }

    public function testAccessGrantedWhenUserIsAdmin(): void
    {
        /** @var User&\PHPUnit\Framework\MockObject\MockObject $user*/
        $user = $this->createMock(User::class);
        $user->method('hasRole')->with('ROLE_ADMIN')->willReturn(true);

        $blogPost = new BlogPost();
        /** @var User&\PHPUnit\Framework\MockObject\MockObject $user1*/
        $user1 = $this->createMock(User::class);
        $blogPost->setAuthor($user1); // Autre auteur

        $token = $this->createTokenMock($user);

        $result = $this->voter->vote($token, $blogPost, [BlogPostVoter::DELETE]);
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result, 'L\'admin devrait avoir accès');
    }

    public function testAccessDeniedWhenUserIsUnauthorized(): void
    {
        /** @var User&\PHPUnit\Framework\MockObject\MockObject $user*/
        $user = $this->createMock(User::class);
        $user->method('hasRole')->with('ROLE_ADMIN')->willReturn(false);

        $blogPost = new BlogPost();
        /** @var User&\PHPUnit\Framework\MockObject\MockObject $user1*/
        $user1 = $this->createMock(User::class);
        $blogPost->setAuthor($user1); // Autre auteur

        $token = $this->createTokenMock($user);

        $result = $this->voter->vote($token, $blogPost, [BlogPostVoter::EDIT]);
        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result, 'Accès refusé pour utilisateur non autorisé');
    }

    public function testAccessDeniedWhenUserIsNotAuthenticated(): void
    {
        $blogPost = new BlogPost();
        /** @var User&\PHPUnit\Framework\MockObject\MockObject $user*/
        $user = $this->createMock(User::class);
        $blogPost->setAuthor($user);

        $token = $this->createTokenMock(null);

        $result = $this->voter->vote($token, $blogPost, [BlogPostVoter::DELETE]);
        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result, 'Accès refusé pour utilisateur non authentifié');
    }

    // Méthodes utilitaires

    private function createTokenMock(?User $user): TokenInterface
    {
        /** @var TokenInterface&\PHPUnit\Framework\MockObject\MockObject $token*/
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }
}
