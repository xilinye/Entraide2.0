<?php

namespace App\Controller;

use App\Entity\BlogPost;
use App\Form\{BlogPostFormType, SearchBlogType};
use App\Repository\BlogPostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/blog', name: 'app_blog_')]
#[IsGranted('ROLE_USER')]
class BlogController extends AbstractController
{
    public function __construct(
        private readonly BlogPostRepository $blogPostRepository,
        private readonly EntityManagerInterface $em,
        private readonly SluggerInterface $slugger
    ) {}

    #[Route('/', name: 'index')]
    public function index(Request $request): Response
    {
        $form = $this->createForm(SearchBlogType::class);
        $form->handleRequest($request);

        $query = $form->get('query')?->getData();

        $posts = $this->blogPostRepository->search($query);

        return $this->render('blog/index.html.twig', [
            'posts' => $posts,
            'searchForm' => $form->createView(),
            'searchQuery' => $query
        ]);
    }

    #[Route('/nouveau', name: 'new')]
    public function new(Request $request): Response
    {
        $post = new BlogPost();
        $form = $this->createForm(BlogPostFormType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $post->setAuthor($this->getUser());
            $post->computeSlug($this->slugger);

            $this->em->persist($post);
            $this->em->flush();

            $this->addFlash('success', 'Article publié avec succès');
            return $this->redirectToRoute('app_blog_show', ['slug' => $post->getSlug()]);
        }

        return $this->render('blog/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{slug}', name: 'show')]
    public function show(string $slug): Response
    {
        $post = $this->blogPostRepository->findOneBySlug($slug);

        if (!$post) {
            throw $this->createNotFoundException('Article non trouvé');
        }

        return $this->render('blog/show.html.twig', [
            'post' => $post,
        ]);
    }
}
