<?php

namespace Silverback\ApiComponentBundle\Form\Type;

use Silverback\ApiComponentBundle\Entity\User\User;
use Silverback\ApiComponentBundle\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Security;

class NewUsernameType extends AbstractType
{
    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $data = $this->security->getUser();
        $help = null;
        if ($data instanceof User && $data->getNewUsername()) {
            $help = sprintf('You have requested to change your email to `%s`. Please check your inbox to validate this email address.', $data->getNewUsername());
        }
        $builder
            ->add('newUsername', EmailType::class, [
                'label' => 'Login Email',
                'attr' => ['autocomplete' => 'username email'],
                'data' => $data ? $data->getUsername() : null,
                'help' => $help
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
            'data_class' => User::class,
            'validation_groups' => ['new_username'],
            'empty_data' => $this->security->getUser(),
            'method' => 'POST'
        ]);
    }
}
