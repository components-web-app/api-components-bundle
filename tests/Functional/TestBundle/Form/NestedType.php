<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Form;

use Silverback\ApiComponentsBundle\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\Length;

class NestedType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'csrf_protection' => false,
                'attr' => [
                    'novalidate' => 'novalidate',
                ],
                'post_app_proxy' => '/proxy',
            ]
        );
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(
                'children',
                CollectionType::class,
                [
                    'entry_type' => ChildType::class,
                    'label' => 'Children',
                    'allow_add' => true,
                    'allow_delete' => true,
                    'by_reference' => false,
                    'required' => true,
                    'error_bubbling' => false,
                    'empty_data' => [new ChildType()],
                    'constraints' => [
                        new Count(
                            [
                                'min' => 1,
                                'minMessage' => 'At least one child is required with a name',
                            ]
                        ),
                    ],
                ]
            )
            ->add(
                'text_children',
                CollectionType::class,
                [
                    'entry_type' => TextType::class,
                    'label' => 'Text Children',
                    'allow_add' => true,
                    'allow_delete' => true,
                    'by_reference' => false,
                    'required' => false,
                    'error_bubbling' => false,
                    'empty_data' => [],
                    'constraints' => [
                        new All(
                            [
                                new Length(
                                    [
                                        'min' => 2,
                                        'minMessage' => 'Must be at least 2 characters',
                                    ]
                                ),
                            ]
                        ),
                    ],
                ]
            );
    }
}
