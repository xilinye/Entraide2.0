<?php

namespace App\Tests\Form;

use App\Form\SearchBlogType;
use Symfony\Component\Form\Test\TypeTestCase;

class SearchBlogTypeTest extends TypeTestCase
{
    public function testFormConfiguration(): void
    {
        $form = $this->factory->create(SearchBlogType::class);

        $this->assertTrue($form->has('query'));
        $this->assertEquals('GET', $form->getConfig()->getMethod());
        $this->assertFalse($form->getConfig()->getOption('csrf_protection'));
    }
}
