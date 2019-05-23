<?php

namespace Silverback\ApiComponentBundle\Form\Type;

use Silverback\ApiComponentBundle\Entity\User\User;
use Silverback\ApiComponentBundle\Entity\User\UserInterface;
use Silverback\ApiComponentBundle\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Security;

class ChangePasswordType extends AbstractType
{
    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var UserInterface $user */
        $user = $builder->getEmptyData();

        $builder
            ->add('username', HiddenType::class, [
                'attr' => ['autocomplete' => 'username'],
                'data' => $user->getUsername()
            ])
            ->add('oldPassword', PasswordType::class, [
                'label' => 'Current password',
                'attr' => ['autocomplete' => 'current-password']
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'The passwords you entered are not the same',
                'first_options'  => [
                    'label' => 'Password',
                    'attr' => ['autocomplete' => 'new-password']
                ],
                'second_options' => [
                    'label' => 'Repeat Password',
                    'attr' => ['autocomplete' => 'new-password']
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
            'attr' => [
                'novalidate' => 'novalidate'
            ],
            'data_class' => UserInterface::class,
            'validation_groups' => ['change_password'],
            'empty_data' => $this->security->getUser()
        ]);
    }
}
