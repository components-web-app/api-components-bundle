<?php

namespace Silverback\ApiComponentBundle\Filter\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\Filter\SQLFilter;
use Silverback\ApiComponentBundle\Entity\PublishableInterface;

final class PublishableFilter extends SQLFilter
{
    /** @var Expr */
    private $exprBuilder;

    public function setExpressionBuilder(Expr $exprBuilder): void
    {
        $this->exprBuilder = $exprBuilder;
    }

    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias): string
    {
        if (null === $this->exprBuilder) {
            throw new \RuntimeException(sprintf('An expression builder. Be sure to call "%s::setExpressionBuilder()".', __CLASS__));
        }

        if (!$this->supportsEntity($targetEntity)) {
            return '';
        }

        return $this->getWhereStatement($targetTableAlias);
    }

    private function supportsEntity(ClassMetadata $targetEntity): bool
    {
        if (!empty($targetEntity->subClasses)) {
            $highestSubclass = $targetEntity->subClasses[max(\count($targetEntity->subClasses)-1, 0)];
            return is_subclass_of($highestSubclass, PublishableInterface::class);
        }
        $reflection = $targetEntity->getReflectionClass();
        return $reflection->implementsInterface(PublishableInterface::class);
    }

    private function getWhereStatement(string $alias): string
    {
        $this->setParameter('published', true);
        $this->setParameter('published_date', date('Y-m-d H:i:s'));

        $pColumn = sprintf('%s.published', $alias);
        $stmt = '(' . $this->exprBuilder->orX(
            $this->exprBuilder->isNull($pColumn),
            $this->exprBuilder->eq($pColumn, $this->getParameter('published'))
        ) . ')';

        $pdColumn = sprintf('%s.published_date', $alias);
        $stmt .= ' AND (' . $this->exprBuilder->orX(
            $this->exprBuilder->isNull($pdColumn),
            $this->exprBuilder->gte($pdColumn, $this->getParameter('published_date'))
        ) . ')';

        return $stmt;
    }
}
