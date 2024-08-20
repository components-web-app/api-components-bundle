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
use Silverback\ApiComponentsBundle\Entity\User\UserInterface;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Form\AbstractType;
use Silverback\ApiComponentsBundle\Repository\User\UserRepositoryInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ChangePasswordType extends AbstractType
{
    private Security $security;
    private UserRepositoryInterface $userRepository;
    private string $userClass;

    public function __construct(Security $security, UserRepositoryInterface $userRepository, string $userClass)
    {
        if (!is_subclass_of($userClass, AbstractUser::class)) {
            throw new InvalidArgumentException(\sprintf('The user class `%s` provided to the form `%s` must extend `%s`', $userClass, __CLASS__, AbstractUser::class));
        }
        $this->security = $security;
        $this->userRepository = $userRepository;
        $this->userClass = $userClass;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var UserInterface|null $user */
        $user = $builder->getEmptyData();

        $builder
            ->add(
                'username',
                // cannot be HiddenType otherwise it will be null if empty - see: https://github.com/symfony/symfony/issues/39148
                TextType::class,
                [
                    'empty_data' => '',
                    'attr' => ['autocomplete' => 'username'],
                    'data' => $user ? $user->getUsername() : '',
                    'disabled' => true,
                ]
            )
            ->add(
                'oldPassword',
                PasswordType::class,
                [
                    'label' => 'Current password',
                    'attr' => ['autocomplete' => 'current-password'],
                ]
            )
            ->add(
                'plainPassword',
                RepeatedType::class,
                [
                    'type' => PasswordType::class,
                    'invalid_message' => 'The passwords you entered are not the same.',
                    'first_options' => [
                        'label' => 'Password',
                        'attr' => ['autocomplete' => 'new-password'],
                    ],
                    'second_options' => [
                        'label' => 'Repeat Password',
                        'attr' => ['autocomplete' => 'new-password'],
                    ],
                ]
            );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        /** @var AbstractUser $securityUser */
        $securityUser = $this->security->getUser();

        // With JWT, we are not hitting the database for the user, but in this case we should be.
        // This is so we can detect change sets in the form listeners.
        $databaseUser = $this->userRepository->find($securityUser->getId());

        $resolver->setDefaults(
            [
                'csrf_protection' => false,
                'attr' => [
                    'novalidate' => 'novalidate',
                ],
                'data_class' => $this->userClass,
                'validation_groups' => ['User:password:create', 'User:password:change'],
                'empty_data' => $databaseUser,
            ]
        );
    }
}
