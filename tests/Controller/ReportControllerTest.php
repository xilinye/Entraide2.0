<?php

namespace App\Tests\Controller;

use App\Controller\ReportController;
use App\Repository\{
    UserRepository,
    MessageRepository,
    BlogPostRepository,
    ForumRepository,
    ForumResponseRepository,
    EventRepository
};
use App\Entity\{User, Skill};
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Twig\Environment as TwigEnvironment;

class ReportControllerTest extends TestCase
{
    private $userRepository;
    private $messageRepository;
    private $blogPostRepository;
    private $forumRepository;
    private $forumResponseRepository;
    private $eventRepository;
    private $em;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->messageRepository = $this->createMock(MessageRepository::class);
        $this->blogPostRepository = $this->createMock(BlogPostRepository::class);
        $this->forumRepository = $this->createMock(ForumRepository::class);
        $this->forumResponseRepository = $this->createMock(ForumResponseRepository::class);
        $this->eventRepository = $this->createMock(EventRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
    }

    public function testEngagementReportPdf(): void
    {
        // Mock repository counts
        $this->userRepository->expects($this->once())
            ->method('count')
            ->with([])
            ->willReturn(10);

        $this->messageRepository->expects($this->once())
            ->method('count')
            ->with([])
            ->willReturn(20);

        $this->blogPostRepository->expects($this->once())
            ->method('count')
            ->with([])
            ->willReturn(5);

        $this->forumRepository->expects($this->once())
            ->method('count')
            ->with([])
            ->willReturn(15);

        $this->forumResponseRepository->expects($this->once())
            ->method('count')
            ->with([])
            ->willReturn(30);

        $this->eventRepository->expects($this->once())
            ->method('count')
            ->with([])
            ->willReturn(8);

        $request = new Request(['format' => 'pdf']);

        $controller = new ReportController(
            $this->userRepository,
            $this->messageRepository,
            $this->blogPostRepository,
            $this->forumRepository,
            $this->forumResponseRepository,
            $this->eventRepository,
            $this->em
        );

        // Mock Twig and container
        $twig = $this->createMock(TwigEnvironment::class);
        $twig->method('render')->willReturn('<html>PDF Content</html>');

        /** @var ContainerInterface&\PHPUnit\Framework\MockObject\MockObject $container*/
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(function ($id) {
            return $id === 'twig';
        });
        $container->method('get')->willReturnCallback(function ($id) use ($twig) {
            if ($id === 'twig') {
                return $twig;
            }
            throw new ServiceNotFoundException($id);
        });
        $container->method('getParameter')->with('kernel.project_dir')->willReturn('/project/dir');

        $controller->setContainer($container);

        $response = $controller->engagementReport($request);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString(
            'attachment; filename="rapport_engagement_',
            $response->headers->get('Content-Disposition')
        );
    }
}
