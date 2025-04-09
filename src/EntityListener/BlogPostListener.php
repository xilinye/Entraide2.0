<?php

namespace App\EntityListener;

use App\Entity\BlogPost;
use Symfony\Component\String\Slugger\SluggerInterface;

class BlogPostListener
{
    public function __construct(
        private SluggerInterface $slugger
    ) {}

    public function prePersist(BlogPost $post): void
    {
        $post->computeSlug($this->slugger);
    }

    public function preUpdate(BlogPost $post): void
    {
        $post->computeSlug($this->slugger);
    }
}