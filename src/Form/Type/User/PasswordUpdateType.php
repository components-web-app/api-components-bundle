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
use Silverback\ApiComponentsBundle\Helper\Form\FormSubmitHelper;
use Silverback\ApiComponentsBundle\Repository\User\UserRepositoryInterface;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class PasswordUpdateType extends AbstractType
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly UserRepositoryInterface $userRepository,
        private readonly string $userClass,
    ) {
        if (!is_subclass_of($this->userClass, AbstractUser::class)) {
            throw new InvalidArgumentException(sprintf('The user class `%s` provided to the form `%s` must extend `%s`', $this->userClass, __CLASS__, AbstractUser::class));
        }
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $prePrePopulatedUser = $this->getPrePopulatedUser();
        $builder
            ->add(
                'username',
                // cannot be HiddenType otherwise it will be null if empty - see: https://github.com/symfony/symfony/issues/39148
                TextType::class,
                [
                    'empty_data' => '',
                    'data' => $prePrePopulatedUser?->getUsername(),
                    'attr' => [
                        'autocomplete' => 'username',
                    ],
                ]
            )
            ->add(
                'plainNewPasswordConfirmationToken',
                HiddenType::class,
                [
                    'data' => $prePrePopulatedUser?->plainNewPasswordConfirmationToken,
                    'attr' => [
                        'placeholder' => '',
                    ],
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
                        'class' => 'is-large is-success is-fullwidth',
                    ],
                    'label' => 'Login',
                ]
            );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $data = $this->getPrePopulatedUser();
        $resolver->setDefaults(
            [
                'csrf_protection' => false,
                'attr' => [
                    'id' => 'password_update_form',
                    'novalidate' => 'novalidate',
                ],
                FormSubmitHelper::FORM_REALTIME_VALIDATE_DISABLED => true,
                'data_class' => $this->userClass,
                'empty_data' => function (FormInterface $form) {
                    return $this->userRepository->findOneWithPasswordResetToken($form->get('username')->getData()) ?? $this->getPrePopulatedUser();
                },
                'validation_groups' => ['User:password:create'],
            ]
        );
    }

    private function getPrePopulatedUser(): ?AbstractUser
    {
        if (!$request = $this->requestStack->getMainRequest()) {
            return null;
        }
        /**
         * @var AbstractUser $user
         */
        $user = new $this->userClass();
        $query = $request->query;
        $user->setUsername($query->get('username', ''));
        $user->plainNewPasswordConfirmationToken = $query->get('token');

        return $user;
    }
}
