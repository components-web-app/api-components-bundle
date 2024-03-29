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

use Silverback\ApiComponentsBundle\Form\AbstractType;
use Silverback\ApiComponentsBundle\Helper\Form\FormSubmitHelper;
use Silverback\ApiComponentsBundle\Model\Form\LoginForm;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\RouterInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UserLoginType extends AbstractType
{
    private RouterInterface $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
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
                'password',
                PasswordType::class,
                [
                    'label' => 'Password',
                    'attr' => ['autocomplete' => 'current-password'],
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
            )
            // js server will look for this to know where to send login request to
            ->add(
                '_action',
                HiddenType::class,
                [
                    'data' => $this->router->generate('api_components_login_check'),
                ]
            );
    }

    public function getBlockPrefix(): ?string
    {
        return '';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // Post to the js server to store credentials in session
        $resolver->setDefaults(
            [
                'csrf_protection' => false,
                'data_class' => LoginForm::class,
                'attr' => [
                    'id' => 'login_form',
                    'novalidate' => 'novalidate',
                ],
                'action' => '/login',
                FormSubmitHelper::FORM_REALTIME_VALIDATE_DISABLED => true,
                FormSubmitHelper::FORM_API_DISABLED => true,
            ]
        );
    }
}
