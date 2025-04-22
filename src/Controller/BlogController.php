<?php

namespace App\Controller;

use App\Entity\{BlogPost, Rating};
use App\Form\{BlogPostFormType, SearchBlogType, RatingType};
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
    public function show(BlogPost $post, Request $request): Response
    {
        $existingRating = $this->em->getRepository(Rating::class)->findOneBy([
            'blogPost' => $post,
            'rater' => $this->getUser()
        ]);

        // Crée ou récupère la notation
        $rating = $existingRating ?? new Rating();

        // Pré-remplit les associations pour les nouvelles notations
        if (!$existingRating) {
            $rating->setBlogPost($post)
                ->setRater($this->getUser())
                ->setRatedUser($post->getAuthor());
        }

        $form = $this->createForm(RatingType::class, $rating);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($post->getAuthor() === $this->getUser()) {
                $this->addFlash('error', 'Vous ne pouvez pas noter votre propre article');
                return $this->redirectToRoute('app_blog_show', ['slug' => $post->getSlug()]);
            }

            $this->em->persist($rating);
            $this->em->flush();

            $this->addFlash('success', $existingRating ? 'Note mise à jour !' : 'Merci pour votre notation !');
            return $this->redirectToRoute('app_blog_show', ['slug' => $post->getSlug()]);
        } elseif ($form->isSubmitted()) {
            // Log des erreurs
            $errors = $form->getErrors(true);
            foreach ($errors as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        $ratings = $this->em->getRepository(Rating::class)->findBy(
            ['blogPost' => $post],
            ['createdAt' => 'DESC']
        );

        $averageRating = $this->em->getRepository(Rating::class)->getAverageForBlogPost($post);

        return $this->render('blog/show.html.twig', [
            'post' => $post,
            'ratingForm' => $form->createView(),
            'ratings' => $ratings,
            'averageRating' => $averageRating,
            'hasRated' => (bool)$existingRating
        ]);
    }

    #[Route('/{slug}/edit', name: 'edit')]
    #[IsGranted('EDIT', 'post')]
    public function edit(Request $request, BlogPost $post): Response
    {
        $originalTitle = $post->getTitle();

        $form = $this->createForm(BlogPostFormType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Regénérer le slug seulement si le titre a changé
            if ($post->getTitle() !== $originalTitle) {
                $post->computeSlug($this->slugger);
            }

            $this->em->flush();

            $this->addFlash('success', 'Article mis à jour avec succès');
            return $this->redirectToRoute('app_blog_show', ['slug' => $post->getSlug()]);
        }

        return $this->render('blog/edit.html.twig', [
            'form' => $form->createView(),
            'post' => $post,
        ]);
    }

    #[Route('/{slug}/delete', name: 'delete', methods: ['POST'])]
    #[IsGranted('DELETE', 'post')]
    public function delete(Request $request, BlogPost $post): Response
    {
        if ($this->isCsrfTokenValid('delete' . $post->getId(), $request->request->get('_token'))) {
            $this->em->remove($post);
            $this->em->flush();
            $this->addFlash('success', 'Article supprimé avec succès');
        }

        return $this->redirectToRoute('app_blog_index');
    }
}
