<?php

namespace Silverback\ApiComponentBundle\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Silverback\ApiComponentBundle\Entity\User\User;
use Silverback\ApiComponentBundle\Filter\Doctrine\PublishableFilter;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class PublishableConfigurator
{
    private $tokenStorage;
    private $authorizationChecker;
    private $em;

    public function __construct(EntityManagerInterface $entityManager, TokenStorageInterface $tokenStorage, AuthorizationCheckerInterface $checker)
    {
        $this->em = $entityManager;
        $this->tokenStorage = $tokenStorage;
        $this->authorizationChecker = $checker;
    }

    public function onKernelRequest(): void
    {
        if ($this->isAuthorized()) {
            return;
        }
        /** @var PublishableFilter $filter */
        $filter = $this->em->getFilters()->enable('publishable');
        $filter->setExpressionBuilder(new Expr());
    }

    private function isAuthorized(): bool
    {
        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return false;
        }
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }
        return $this->authorizationChecker->isGranted('ROLE_ADMIN');
    }
}
