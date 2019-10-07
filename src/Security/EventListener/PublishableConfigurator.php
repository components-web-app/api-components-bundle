<?php

namespace Silverback\ApiComponentBundle\Security\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Silverback\ApiComponentBundle\Filter\Doctrine\PublishableFilter;

final class PublishableConfigurator
{
    private $authorizedChecker;
    private $em;

    public function __construct(EntityManagerInterface $entityManager, AuthorizedChecker $authorizedChecker)
    {
        $this->em = $entityManager;
        $this->authorizedChecker = $authorizedChecker;
    }

    public function onKernelRequest(): void
    {
        if ($this->authorizedChecker->isAuthorized()) {
            return;
        }
        /** @var PublishableFilter $filter */
        $filter = $this->em->getFilters()->enable('publishable');
        $filter->setExpressionBuilder(new Expr());
    }
}
