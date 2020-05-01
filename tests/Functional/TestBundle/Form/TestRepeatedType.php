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
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TestRepeatedType extends AbstractType
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
            ->add('repeat', RepeatedType::class, [
                'type' => TextType::class,
                'invalid_message' => 'The repeat fields must match.',
                'options' => ['attr' => ['autocomplete' => 'new-password']],
                'required' => true,
            ]);
    }
}
