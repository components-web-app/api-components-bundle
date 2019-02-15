<?php

namespace Silverback\ApiComponentBundle\Form\Handler;

use Silverback\ApiComponentBundle\Entity\User\User;
use Silverback\ApiComponentBundle\Security\TokenGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Silverback\ApiComponentBundle\Entity\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;

class NewUsernameHandler implements FormHandlerInterface, ContextProviderInterface
{
    private $entityManager;
    private $tokenGenerator;

    public function __construct(
        EntityManagerInterface $entityManager,
        TokenGenerator $tokenGenerator
    ) {
        $this->entityManager = $entityManager;
        $this->tokenGenerator = $tokenGenerator;
    }

    /**
     * @param Form $form
     * @param User $data
     * @param Request $request
     * @return User
     */
    public function success(Form $form, $data, Request $request): User
    {
        // send an email to the new email address with the confirmation token to validate the new email
        $data->setUsernameConfirmationToken($this->tokenGenerator->generateToken());
        $this->entityManager->persist($data);
        $this->entityManager->flush();
        return $data;
    }

    public function getContext(): ?array
    {
        return ['groups' => ['new_username']];
    }
}
