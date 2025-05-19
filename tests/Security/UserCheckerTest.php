<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;

class UserCheckerTest extends TestCase
{
    public function testCheckPreAuthWithVerifiedUser(): void
    {
        $user = new User();
        $user->setIsVerified(true);
        $checker = new UserChecker();

        // No exception should be thrown
        $checker->checkPreAuth($user);
        $this->assertTrue(true); // Assertion to indicate the test passed
    }

    public function testCheckPreAuthWithUnverifiedUser(): void
    {
        $user = new User();
        $checker = new UserChecker();

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('UNVERIFIED_ACCOUNT');

        $checker->checkPreAuth($user);
    }

    public function testCheckPreAuthWithInvalidUserType(): void
    {
        /** @var UserInterface&\PHPUnit\Framework\MockObject\MockObject $user*/
        $user = $this->createMock(UserInterface::class);
        $checker = new UserChecker();

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Type d\'utilisateur non supporté.');

        $checker->checkPreAuth($user);
    }
}
