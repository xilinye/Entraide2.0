<?php

namespace App\Tests\Form;

use App\Form\SearchBlogType;
use Symfony\Component\Form\Extension\Core\Type\SearchType as SymfonySearchType;
use Symfony\Component\Form\Test\TypeTestCase;

class SearchBlogTypeTest extends TypeTestCase
{
    public function testFormConfiguration(): void
    {
        $form = $this->factory->create(SearchBlogType::class);
        $view = $form->createView();
        $field = $view->children['query'];

        // Vérification de l'existence du champ
        $this->assertArrayHasKey('query', $view->children);

        // Vérification du type de champ
        $fieldType = $form->get('query')->getConfig()->getType()->getInnerType();
        $this->assertInstanceOf(SymfonySearchType::class, $fieldType);

        // Options du formulaire
        $this->assertEquals('GET', $form->getConfig()->getMethod());
        $this->assertFalse($form->getConfig()->getOption('csrf_protection'));

        // Attributs HTML
        $this->assertEquals('Rechercher un article...', $field->vars['attr']['placeholder']);
        $this->assertEquals('form-control-lg', $field->vars['attr']['class']);

        // Options du champ
        $this->assertFalse($field->vars['required']);
        $this->assertFalse($field->vars['label']);
    }
}
