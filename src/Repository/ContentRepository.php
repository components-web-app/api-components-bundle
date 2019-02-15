<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use PHPCR\RepositoryException;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method AbstractContent|null find($id, $lockMode = null, $lockVersion = null)
 * @method AbstractContent|null findOneBy(array $criteria, array $orderBy = null)
 * @method AbstractContent[]    findAll()
 * @method AbstractContent[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ContentRepository extends ServiceEntityRepository
{
    private $registry;

    public function __construct(RegistryInterface $registry)
    {
        $this->registry = $registry;
        parent::__construct($registry, AbstractContent::class);
    }

    public function findPageByType(string $entityClass): Collection
    {
        if (!is_subclass_of($entityClass, AbstractContent::class)) {
            throw new RepositoryException(sprintf('The entity class must be a subclass of %s', AbstractContent::class));
        }
        $childRepository = new ServiceEntityRepository($this->registry, $entityClass);
        $result = new ArrayCollection($childRepository->findAll());

        $uow = $childRepository->getEntityManager()->getUnitOfWork();
        $scheduledInsertions = $uow->getScheduledEntityInsertions();
        foreach ($scheduledInsertions as $scheduledInsertion) {
            if (is_a($scheduledInsertion, $entityClass)) {
                $result->add($scheduledInsertion);
            }
        }
        return $result;
    }
}
