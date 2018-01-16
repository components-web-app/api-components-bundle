<?php

namespace Silverback\ApiComponentBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ContactType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            [
                'csrf_protection' => false,
                'attr' => [
                    'novalidate' => 'novalidate'
                ]
            ]
        );
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add(
                'name',
                TextType::class,
                [
                    'attr' => [
                        'placeholder' => 'Your name'
                    ],
                    'label' => 'Your Name',
                    'constraints' => [
                        new NotBlank(
                            [
                                "message" => "Please provide your name"
                            ]
                        ),
                    ]
                ]
            )
            ->add(
                'subject',
                ChoiceType::class,
                [
                    'attr' => [
                        'placeholder' => 'Subject'
                    ],
                    'label' => 'Regarding',
                    'choices' => [
                        'Please select' => '',
                        'General enquiry' => 'enquiry',
                        'Anything else' => 'other',
                        'Invalid option' => '-'
                    ],
                    // 'choices_as_values' => true,
                    'choice_attr' => function ($val, $key, $index) {
                        return $val === '' ? ['disabled' => ''] : [];
                    },
                    'constraints' => [
                        new NotBlank(
                            [
                                "message" => "Please select what the message is regarding"
                            ]
                        ),
                        new Length(
                            [
                                "min" => 2,
                                "minMessage" => "The option selected is invalid"
                            ]
                        )
                    ]
                ]
            )
            ->add(

                'email',
                EmailType::class,
                [
                    'attr' => [
                        'placeholder' => 'Your email address'
                    ],
                    'label' => 'Your Email',
                    'constraints' => [
                        new NotBlank(
                            [
                                "message" => "Please provide a valid email"
                            ]
                        ),
                        new Email(
                            [
                                "message" => "Your email doesn't seems to be valid"
                            ]
                        ),
                    ]
                ]
            )
            ->add(

                'message',
                TextareaType::class,
                [
                    'attr' => [
                        'placeholder' => 'Your message here'
                    ],
                    'label' => 'Message',
                    'constraints' => [
                        new NotBlank(
                            [
                                "message" => "Please provide a message here"
                            ]
                        )
                    ]
                ]
            )
            ->add(
                'developer',
                ChoiceType::class,
                [
                    'label' => 'Are you a developer?',
                    'choices' => [
                        'Yes' => 'yes',
                        'No' => 'no'
                    ],
                    'choice_attr' => function () {
                        return ['class' => 'custom'];
                    },
                    'expanded' => true,
                    'required' => true,
                    'constraints' => [
                        new NotBlank(
                            [
                                "message" => "Please select if you are a developer"
                            ]
                        )
                    ]
                ]
            )
            ->add(
                'randomCheckbox',
                CheckboxType::class,
                [
                    'attr' => [
                        'class' => 'custom'
                    ],
                    'label' => 'To check or not to check? <b>That</b> is a question',
                    'required' => true,
                    'constraints' => [
                        new NotBlank(
                            [
                                "message" => "The correct answer to the question is to check, it is required"
                            ]
                        )
                    ]
                ]
            )
            ->add(
                'interests',
                ChoiceType::class,
                [
                    'label' => 'Select at least one food',
                    'choices' => [
                        'Pizza' => 'pizza',
                        'Chips' => 'chips',
                        'Vegetables' => 'veggie'
                    ],
                    'choice_attr' => function () {
                        return ['class' => 'custom'];
                    },
                    'expanded' => true,
                    'multiple' => true,
                    'constraints' => [
                        new NotBlank(
                            [
                                "message" => "An interest is needed"
                            ]
                        )
                    ]
                ]
            )
            ->add(
                'other_interests',
                ChoiceType::class,
                [
                    'label' => 'Select at least one',
                    'choices' => [
                        'Trump' => 'trump',
                        'Obama' => 'obama',
                        'Corbyn' => 'corbyn',
                        'May' => 'may'
                    ],
                    'choice_attr' => function () {
                        return ['class' => 'custom'];
                    },
                    'expanded' => false,
                    'multiple' => true,
                    'required' => true,
                    'constraints' => [
                        new NotBlank(
                            [
                                "message" => "Please select at least one"
                            ]
                        )
                    ]
                ]
            )
            ->add('submit', SubmitType::class, [
                'attr' => [
                    'class' => 'is-large is-primary is-fullwidth'
                ],
                'label' => 'Send'
            ]);
    }
}