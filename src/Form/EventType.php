<?php

namespace App\Form;

use App\Entity\Event;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class EventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Event Title',
                'attr' => [
                    'class' => 'mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm',
                    'placeholder' => 'Enter event title',
                ],
                'constraints' => [
                    new NotBlank(message: 'Please enter an event title.'),
                    new Length(
                        min: 3,
                        max: 255,
                        minMessage: 'Event title must be at least {{ limit }} characters long.',
                        maxMessage: 'Event title cannot be longer than {{ limit }} characters.'
                    ),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm',
                    'placeholder' => 'Describe your event...',
                    'rows' => 4,
                ],
            ])
            ->add('location', TextType::class, [
                'label' => 'Location',
                'required' => false,
                'attr' => [
                    'class' => 'mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm',
                    'placeholder' => 'Event location',
                ],
            ])
            ->add('startDate', DateTimeType::class, [
                'label' => 'Start Date & Time',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm',
                ],
                'constraints' => [
                    new NotBlank(message: 'Please select a start date and time.'),
                    // Temporarily removed future date validation for debugging
                    // new GreaterThanOrEqual(
                    //     value: new \DateTime(),
                    //     message: 'Start date must be in the future.'
                    // ),
                ],
            ])
            ->add('endDate', DateTimeType::class, [
                'label' => 'End Date & Time',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm',
                ],
            ])
            ->add('maxParticipants', IntegerType::class, [
                'label' => 'Maximum Participants',
                'required' => false,
                'attr' => [
                    'class' => 'mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm',
                    'placeholder' => 'Leave empty for unlimited',
                    'min' => 1,
                ],
                'constraints' => [
                    new GreaterThan(
                        value: 0,
                        message: 'Maximum participants must be greater than 0.'
                    ),
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'Planned' => Event::STATUS_PLANNED,
                    'Cancelled' => Event::STATUS_CANCELLED,
                    'Completed' => Event::STATUS_COMPLETED,
                ],
                'attr' => [
                    'class' => 'mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm',
                ],
                'constraints' => [
                    new NotBlank(message: 'Please select a status.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Event::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'event_form',
        ]);
    }
}
