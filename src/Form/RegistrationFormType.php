<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationFormType extends AbstractType
{
    private const CSS_INPUT_CLASS = 'w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500';
    private const MIN_PASSWORD_LENGTH = 6;
    private const MAX_PASSWORD_LENGTH = 4096;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email Address',
                'attr' => [
                    'class' => self::CSS_INPUT_CLASS,
                    'placeholder' => 'Enter your email address',
                ],
            ])
            ->add('firstName', TextType::class, [
                'label' => 'First Name',
                'required' => false,
                'attr' => [
                    'class' => self::CSS_INPUT_CLASS,
                    'placeholder' => 'Enter your first name',
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last Name',
                'required' => false,
                'attr' => [
                    'class' => self::CSS_INPUT_CLASS,
                    'placeholder' => 'Enter your last name',
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'The password fields must match.',
                'options' => [
                    'attr' => ['class' => self::CSS_INPUT_CLASS],
                ],
                'required' => true,
                'first_options' => [
                    'label' => 'Password',
                    'attr' => ['placeholder' => 'Enter your password'],
                ],
                'second_options' => [
                    'label' => 'Repeat Password',
                    'attr' => ['placeholder' => 'Repeat your password'],
                ],
                'mapped' => false,
                'constraints' => [
                    new NotBlank(
                        message: 'Please enter a password'
                    ),
                    new Length(
                        min: self::MIN_PASSWORD_LENGTH,
                        minMessage: 'Your password should be at least {{ limit }} characters',
                        max: self::MAX_PASSWORD_LENGTH
                    ),
                ],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'label' => 'I agree to the terms and conditions',
                'mapped' => false,
                'constraints' => [
                    new IsTrue(
                        message: 'You should agree to our terms.'
                    ),
                ],
                'attr' => [
                    'class' => 'mr-2',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
