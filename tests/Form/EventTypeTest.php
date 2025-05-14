<?php

namespace App\Tests\Form;

use App\Entity\{Event, User};
use App\Form\EventType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class EventTypeTest extends KernelTestCase
{
    private function createForm(): FormInterface
    {
        self::bootKernel();
        $formFactory = self::getContainer()->get('form.factory');
        $event = new Event();
        $organizer = new User();
        $organizer->setPseudo('organisateur');
        $organizer->setEmail('test@example.com');
        $organizer->setPassword('password');
        $event->setOrganizer($organizer);
        return $formFactory->create(EventType::class, $event);
    }

    public function testFormFields()
    {
        $form = $this->createForm();

        $this->assertTrue($form->has('title'));
        $this->assertTrue($form->has('description'));
        $this->assertTrue($form->has('imageFile'));
        $this->assertTrue($form->has('startDate'));
        $this->assertTrue($form->has('endDate'));
        $this->assertTrue($form->has('location'));
        $this->assertTrue($form->has('maxAttendees'));

        // Vérification supplémentaire du type de champ
        $this->assertInstanceOf(
            \Symfony\Component\Form\Extension\Core\Type\IntegerType::class,
            $form->get('maxAttendees')->getConfig()->getType()->getInnerType()
        );
    }

    public function testSubmitValidData()
    {
        $form = $this->createForm();
        $formData = [
            'title' => 'Test Event',
            'description' => 'Test Description',
            'startDate' => '2025-06-01 12:00',
            'endDate' => '2025-06-01 14:00',
            'location' => 'Test Location',
            'maxAttendees' => 10,
        ];

        $form->submit($formData);
        $this->assertTrue($form->isSynchronized());

        $event = $form->getData();
        $this->assertEquals($formData['title'], $event->getTitle());
        $this->assertEquals($formData['description'], $event->getDescription());
        $this->assertEquals(new \DateTime($formData['startDate']), $event->getStartDate());
        $this->assertEquals(new \DateTime($formData['endDate']), $event->getEndDate());
        $this->assertEquals($formData['location'], $event->getLocation());
        $this->assertEquals($formData['maxAttendees'], $event->getMaxAttendees());
    }

    public function testImageFileValidation()
    {
        $form = $this->createForm();
        $file = $this->createUploadedFile('image/jpeg', 6000000); // 6MB réels
        $form->submit(['imageFile' => $file] + $this->getValidBaseData());

        $this->assertFalse($form->isValid());
        $errors = $form->get('imageFile')->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('5 MB', $errors[0]->getMessage());
    }
    public function testChampsRequis()
    {
        $form = $this->createForm();
        $form->submit([]); // Soumission avec des données vides

        $this->assertFalse($form->isValid());
        $this->assertNotEmpty($form->get('title')->getErrors());
        $this->assertNotEmpty($form->get('startDate')->getErrors());
        $this->assertNotEmpty($form->get('endDate')->getErrors());
        $this->assertNotEmpty($form->get('location')->getErrors());
        $this->assertEmpty($form->get('description')->getErrors()); // Description non requise
    }

    private function getValidBaseData(): array
    {
        return [
            'title' => 'Test Event',
            'description' => 'Description',
            'startDate' => '2025-06-01 12:00',
            'endDate' => '2025-06-01 14:00',
            'location' => 'Test Location',
            'maxAttendees' => 10,
        ];
    }

    private function createUploadedFile(string $mimeType, int $size = null): UploadedFile
    {
        $filePath = tempnam(sys_get_temp_dir(), 'test');

        if ($size) {
            // Créer un fichier vide de la taille spécifiée
            file_put_contents($filePath, str_repeat('0', $size));
        } else {
            // Image JPEG valide 1x1 (167 bytes)
            file_put_contents($filePath, base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBgaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD3+iiigD//2Q=='));
        }

        return new UploadedFile($filePath, 'test.jpg', $mimeType, null, true);
    }
    public function testEndDateAfterStartDate()
    {
        $form = $this->createForm();
        $form->submit([
            'startDate' => '2023-01-01 14:00', // Date de début POSTERIEURE
            'endDate' => '2023-01-01 12:00',   // Date de fin ANTÉRIEURE
        ] + $this->getValidBaseData());

        $this->assertFalse($form->isValid());
        $this->assertStringContainsString('La date de fin doit être après la date de début', $form->getErrors(true));
    }
    public function testMaxAttendeesPositiveValue()
    {
        $form = $this->createForm();
        $form->submit([
            'maxAttendees' => -5
        ] + $this->getValidBaseData());

        $this->assertFalse($form->isValid());
        $this->assertStringContainsString(
            'Le nombre de participants doit être au moins 1',
            $form->get('maxAttendees')->getErrors()[0]->getMessage()
        );
    }
    public function testValidImageSubmission()
    {
        $form = $this->createForm();
        $file = $this->createUploadedFile('image/jpeg'); // Taille par défaut valide
        $form->submit(['imageFile' => $file] + $this->getValidBaseData());

        $this->assertTrue($form->isValid(), (string)$form->getErrors(true));
    }
    public function testMaxAttendeesBoundary()
    {
        $form = $this->createForm();
        $form->submit([
            'maxAttendees' => 0
        ] + $this->getValidBaseData());

        $this->assertFalse($form->isValid());

        $errors = $form->get('maxAttendees')->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString(
            'doit être au moins 1',
            $errors[0]->getMessage()
        );
    }
    public function testCsrfProtection()
    {
        $form = $this->createForm();
        $this->assertEquals(
            $_ENV['APP_ENV'] !== 'test',
            $form->getConfig()->getOption('csrf_protection')
        );
    }
}
