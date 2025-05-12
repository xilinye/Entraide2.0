<?php

namespace App\Tests\Form;

use App\Entity\Rating;
use App\Form\RatingType;
use Symfony\Component\Form\Test\TypeTestCase;

class RatingTypeTest extends TypeTestCase
{
    public function testSubmitValidScore(): void
    {
        $formData = [
            'score' => 4,
            'comment' => 'Great post!'
        ];

        $model = new Rating();
        $form = $this->factory->create(RatingType::class, $model);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertEquals(4, $model->getScore());
        $this->assertEquals('Great post!', $model->getComment());
    }
}
