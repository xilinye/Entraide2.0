<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Skill;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Création des catégories
        $categories = [];

        foreach (['Développement web', 'Design', 'Marketing', 'DevOps', 'Réseaux', 'IA'] as $name) {
            $category = new Category();
            $category->setName($name);
            $manager->persist($category);
            $categories[$name] = $category; // Stock pour plus tard
        }

        // Création des compétences liées aux catégories
        $skillsData = [
            'Développement web' => ['Symfony', 'React', 'Node.js', 'GraphQL', 'Webpack'],
            'Design' => ['Figma', 'Photoshop'],
            'DevOps' => ['Docker', 'Kubernetes'],
            'Réseaux' => ['TCP/IP', 'DNS', 'DHCP', 'VPN', 'Routing'],
            'IA' => ['Machine Learning', 'Deep Learning', 'Traitement du langage naturel (NLP)', 'Vision par ordinateur', 'Réseaux de neurones',]
        ];

        foreach ($skillsData as $catName => $skills) {
            foreach ($skills as $skillName) {
                $skill = new Skill();
                $skill->setName($skillName);
                $skill->setCategory($categories[$catName]);
                $manager->persist($skill);
            }
        }

        $manager->flush();
    }
}


