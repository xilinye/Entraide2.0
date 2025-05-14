<?php

namespace App\Tests\Form;

use App\Entity\Rating;
use App\Form\RatingType;
use Symfony\Component\Form\Test\TypeTestCase;

class RatingTypeTest extends TypeTestCase
{
    // Test vérifiant un cas valide
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
        $this->assertTrue($form->isValid());
        $this->assertEquals(4, $model->getScore());
        $this->assertEquals('Great post!', $model->getComment());
    }

    // Test de tous les scores valides (1 à 5)
    public function testAllValidScores(): void
    {
        foreach (range(1, 5) as $score) {
            $formData = ['score' => $score, 'comment' => 'Valid'];
            $model = new Rating();
            $form = $this->factory->create(RatingType::class, $model);
            $form->submit($formData);

            $this->assertTrue($form->isValid(), "Échec pour le score $score");
            $this->assertEquals($score, $model->getScore());
        }
    }

    // Test des scores invalides
    public function testInvalidScores(): void
    {
        $invalidScores = [0, 6, 'invalid'];

        foreach ($invalidScores as $score) {
            $formData = ['score' => $score];
            $form = $this->factory->create(RatingType::class, new Rating());
            $form->submit($formData);

            $this->assertFalse($form->isValid());
            // Utilisation du message d'erreur anglais par défaut
            $this->assertStringContainsString(
                'The selected choice is invalid.',
                $form->getErrors(true)
            );
        }
    }

    // Test du commentaire optionnel
    public function testOptionalComment(): void
    {
        $formData = [
            'score' => 2,
            'comment' => '' // Soumission avec commentaire vide
        ];

        $model = new Rating();
        $form = $this->factory->create(RatingType::class, $model);
        $form->submit($formData);

        $this->assertTrue($form->isValid());
        $this->assertEmpty($model->getComment());
    }

    // Test d'absence de score
    public function testMissingScore(): void
    {
        $formData = ['comment' => 'J\'ai oublié le score'];
        $form = $this->factory->create(RatingType::class, new Rating());
        $form->submit($formData);

        $this->assertFalse($form->isValid());

        // Vérifie l'erreur de validation sur le champ 'score'
        $this->assertTrue($form->get('score')->getErrors()->count() > 0);
    }

    // Test de type de données incorrect
    public function testNonIntegerScore(): void
    {
        $formData = [
            'score' => '3', // Chaîne au lieu d'entier
            'comment' => 'Test de type'
        ];

        $model = new Rating();
        $form = $this->factory->create(RatingType::class, $model);
        $form->submit($formData);

        // Le formulaire devrait convertir automatiquement en entier
        $this->assertTrue($form->isValid());
        $this->assertSame(3, $model->getScore());
    }
}
