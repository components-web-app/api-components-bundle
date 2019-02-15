<?php

namespace Silverback\ApiComponentBundle\Form\Type;

use Silverback\ApiComponentBundle\Entity\Form\LoginForm;
use Silverback\ApiComponentBundle\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\RouterInterface;

class LoginType extends AbstractType
{
    private $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', EmailType::class, [
                'attr' => [
                    'placeholder' => '',
                    'autocomplete' => 'username email'
                ],
                'label' => 'Email'
            ])
            ->add('password', PasswordType::class, [
                'label' => 'Password',
                'attr' => ['autocomplete' => 'current-password']
            ])
            ->add('submit', SubmitType::class, [
                'attr' => [
                    'class' => 'is-large is-success is-fullwidth'
                ],
                'label' => 'Login'
            ])
            // js server will look for this to know where to send login request to
            ->add('_action', HiddenType::class, [
                'data' => $this->router->generate('api_login_check')
            ])
        ;
    }

    public function getBlockPrefix(): ?string
    {
        return null;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
            'data_class' => LoginForm::class,
            'attr' => [
                'id' => 'login',
                'novalidate' => 'novalidate'
            ],
            // Post to the js server to store credentials in session
            'action' => '/login',
            'realtime_validate' => false,
            'api_request' => false
        ]);
    }
}
