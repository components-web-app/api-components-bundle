<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Form\Type\User;

use DateTime;
use DateTimeImmutable;
use Silverback\ApiComponentBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentBundle\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserRegisterType extends AbstractType
{
    private string $userClass;

    public function __construct(string $userClass)
    {
        $this->userClass = $userClass;
        if (!is_subclass_of($this->userClass, AbstractUser::class)) {
            throw new InvalidArgumentException(sprintf('The user class `%s` provided to the form `%s` must extend `%s`', $this->userClass, __CLASS__, AbstractUser::class));
        }
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'attr' => [
                    'placeholder' => '',
                    'autocomplete' => 'username',
                ],
                'label' => 'Username',
            ])
//            ->add('emailAddress', EmailType::class, [
//                'attr' => [
//                    'placeholder' => '',
//                    'autocomplete' => 'email',
//                ],
//                'label' => 'Email Address',
//            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'The password fields must match.',
                'options' => ['attr' => ['autocomplete' => 'new-password']],
                'required' => true,
                'first_options' => ['label' => 'Create Password'],
                'second_options' => ['label' => 'Repeat Password'],
            ])
            ->add('submit', SubmitType::class, [
                'attr' => [
                    'class' => 'is-large is-primary is-fullwidth',
                ],
                'label' => 'Register',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        /**
         * @var AbstractUser
         */
        $user = new $this->userClass();
        $user
            ->setCreated(new DateTimeImmutable())
            ->setModified(new DateTime());

        $resolver->setDefaults(
            [
                'csrf_protection' => false,
                'attr' => [
                    'novalidate' => 'novalidate',
                ],
                'data_class' => $this->userClass,
                'empty_data' => $user,
            ]
        );
    }
}
