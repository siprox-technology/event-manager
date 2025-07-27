<?php

namespace App\Tests\Form;

use App\Entity\User;
use App\Form\UserProfileType;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Validator\Validation;

final class UserProfileTypeTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        return [
            new ValidatorExtension($validator),
        ];
    }

    public function testSubmitValidData(): void
    {
        $formData = [
            'email' => 'updated@example.com',
            'firstName' => 'Jane',
            'lastName' => 'Smith',
            'bio' => 'This is my updated bio',
        ];

        $user = new User();
        $user->setEmail('original@example.com');
        $user->setFirstName('John');
        $user->setLastName('Doe');
        $user->setBio('Original bio');

        $form = $this->factory->create(UserProfileType::class, $user);
        $form->submit($formData);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());
        
        // Check that the form data is correctly mapped to the entity
        self::assertEquals('updated@example.com', $user->getEmail());
        self::assertEquals('Jane', $user->getFirstName());
        self::assertEquals('Smith', $user->getLastName());
        self::assertEquals('This is my updated bio', $user->getBio());
    }

    public function testFormHasRequiredFields(): void
    {
        $form = $this->factory->create(UserProfileType::class);
        
        self::assertTrue($form->has('email'));
        self::assertTrue($form->has('firstName'));
        self::assertTrue($form->has('lastName'));
        self::assertTrue($form->has('bio'));
    }

    public function testEmailFieldConfiguration(): void
    {
        $form = $this->factory->create(UserProfileType::class);
        $emailField = $form->get('email');
        
        $config = $emailField->getConfig();
        self::assertTrue($config->getRequired());
    }

    public function testNameFieldsConfiguration(): void
    {
        $form = $this->factory->create(UserProfileType::class);
        
        $firstNameField = $form->get('firstName');
        $lastNameField = $form->get('lastName');
        
        self::assertFalse($firstNameField->getConfig()->getRequired());
        self::assertFalse($lastNameField->getConfig()->getRequired());
    }

    public function testBioFieldConfiguration(): void
    {
        $form = $this->factory->create(UserProfileType::class);
        $bioField = $form->get('bio');
        
        $config = $bioField->getConfig();
        self::assertFalse($config->getRequired()); // Bio should be optional
    }

    public function testFormValidationWithInvalidEmail(): void
    {
        $formData = [
            'email' => 'invalid-email',
            'firstName' => 'Jane',
            'lastName' => 'Smith',
            'bio' => 'Valid bio',
        ];

        $user = new User();
        $form = $this->factory->create(UserProfileType::class, $user);
        $form->submit($formData);

        self::assertTrue($form->isSynchronized());
        self::assertFalse($form->isValid());
        self::assertGreaterThan(0, count($form->get('email')->getErrors()));
    }

    public function testFormValidationWithEmptyRequiredFields(): void
    {
        $formData = [
            'email' => '',
            'firstName' => '',
            'lastName' => '',
            'bio' => 'Bio can be present even when required fields are empty',
        ];

        $user = new User();
        $form = $this->factory->create(UserProfileType::class, $user);
        $form->submit($formData);

        self::assertTrue($form->isSynchronized());
        self::assertFalse($form->isValid());
        
        // Should have errors for required fields
        self::assertGreaterThan(0, count($form->get('email')->getErrors()));
        
        // Name fields are optional so should not have errors when empty
        self::assertEquals(0, count($form->get('firstName')->getErrors()));
        self::assertEquals(0, count($form->get('lastName')->getErrors()));
        
        // Bio should not have errors as it's optional
        self::assertEquals(0, count($form->get('bio')->getErrors()));
    }

    public function testBioCanBeNull(): void
    {
        $formData = [
            'email' => 'test@example.com',
            'firstName' => 'Jane',
            'lastName' => 'Smith',
            'bio' => null,
        ];

        $user = new User();
        $form = $this->factory->create(UserProfileType::class, $user);
        $form->submit($formData);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());
        self::assertNull($user->getBio());
    }

    public function testBioCanBeEmptyString(): void
    {
        $formData = [
            'email' => 'test@example.com',
            'firstName' => 'Jane',
            'lastName' => 'Smith',
            'bio' => '',
        ];

        $user = new User();
        $form = $this->factory->create(UserProfileType::class, $user);
        $form->submit($formData);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());
        self::assertEquals('', $user->getBio());
    }

    public function testBioMaxLengthValidation(): void
    {
        $formData = [
            'email' => 'test@example.com',
            'firstName' => 'Jane',
            'lastName' => 'Smith',
            'bio' => str_repeat('a', 1001), // Over 1000 characters
        ];

        $user = new User();
        $form = $this->factory->create(UserProfileType::class, $user);
        $form->submit($formData);

        self::assertTrue($form->isSynchronized());
        self::assertFalse($form->isValid());
        self::assertGreaterThan(0, count($form->get('bio')->getErrors()));
    }
}
