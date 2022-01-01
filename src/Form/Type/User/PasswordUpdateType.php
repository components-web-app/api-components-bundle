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
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class PasswordUpdateType extends AbstractType
{
    private RequestStack $requestStack;
    private string $userClass;

    public function __construct(RequestStack $requestStack, string $userClass)
    {
        $this->userClass = $userClass;
        if (!is_subclass_of($this->userClass, AbstractUser::class)) {
            throw new InvalidArgumentException(sprintf('The user class `%s` provided to the form `%s` must extend `%s`', $this->userClass, __CLASS__, AbstractUser::class));
        }
        $this->requestStack = $requestStack;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(
                'username',
                HiddenType::class,
                [
                    'attr' => [
                        'autocomplete' => 'username',
                    ],
                ]
            )
            ->add(
                'newPasswordConfirmationToken',
                HiddenType::class,
                [
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
        /**
         * @var AbstractUser
         */
        $user = new $this->userClass();

        $request = $this->requestStack->getMainRequest();
        if ($request) {
            $query = $request->query;
            $user->setUsername($query->get('username'));
            $user->setNewPasswordConfirmationToken($query->get('token'));
        }

        $resolver->setDefaults(
            [
                'csrf_protection' => false,
                'attr' => [
                    'id' => 'password_update_form',
                    'novalidate' => 'novalidate',
                ],
                'action' => '/password/reset',
                FormSubmitHelper::FORM_REALTIME_VALIDATE_DISABLED => true,
                'data_class' => $this->userClass,
                'data' => $user,
                'validation_groups' => ['User:password:create'],
            ]
        );
    }
}
