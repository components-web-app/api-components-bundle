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

namespace Silverback\ApiComponentsBundle\Form\Type\User;

use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UserRegisterType extends AbstractType
{
    private string $userClass;

    public function __construct(string $userClass)
    {
        $this->userClass = $userClass;
        if (!is_subclass_of($this->userClass, AbstractUser::class)) {
            throw new InvalidArgumentException(\sprintf('The user class `%s` provided to the form `%s` must extend `%s`', $this->userClass, __CLASS__, AbstractUser::class));
        }
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(
                'username',
                TextType::class,
                [
                    'empty_data' => '',
                    'attr' => [
                        'placeholder' => '',
                        'autocomplete' => 'username',
                    ],
                    'label' => 'Username',
                ]
            )
            ->add(
                'emailAddress',
                EmailType::class,
                [
                    'label' => 'Email',
                    'attr' => ['autocomplete' => 'email', 'placeholder' => ''],
                    'empty_data' => '',
                ]
            )
            ->add(
                'plainPassword',
                RepeatedType::class,
                [
                    'type' => PasswordType::class,
                    'invalid_message' => 'The password fields must match.',
                    'options' => ['attr' => ['autocomplete' => 'new-password']],
                    'required' => true,
                    'first_options' => ['label' => 'Create Password'],
                    'second_options' => ['label' => 'Repeat Password'],
                ]
            )
            ->add(
                'submit',
                SubmitType::class,
                [
                    'attr' => [
                        'class' => 'is-large is-primary is-fullwidth',
                    ],
                    'label' => 'Register',
                ]
            );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        /**
         * @var AbstractUser
         */
        $user = new $this->userClass();

        $resolver->setDefaults(
            [
                'csrf_protection' => false,
                'attr' => [
                    'novalidate' => 'novalidate',
                ],
                'data_class' => $this->userClass,
                'empty_data' => $user,
                'validation_groups' => ['Default', 'User:password:create'],
            ]
        );
    }
}
