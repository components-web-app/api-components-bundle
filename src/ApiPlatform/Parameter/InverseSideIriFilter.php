<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\ApiPlatform\Parameter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\BackwardCompatibleFilterDescriptionTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\ParameterProviderFilterInterface;
use ApiPlatform\State\ParameterProvider\IriConverterParameterProvider;
use Doctrine\ORM\QueryBuilder;

final class InverseSideIriFilter implements FilterInterface, ParameterProviderFilterInterface
{
    use BackwardCompatibleFilterDescriptionTrait;

    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $parameter = $context['parameter'];
        $property = $parameter->getProperty() ?? $parameter->getKey();
        $value = $parameter->getValue();

        $joinAlias = $queryNameGenerator->generateJoinAlias($property);
        $parameterName = $queryNameGenerator->generateParameterName($property);

        $queryBuilder
            ->innerJoin(\sprintf('%s.%s', $queryBuilder->getRootAliases()[0], $property), $joinAlias)
            ->andWhere(\sprintf('%s IN (:%s)', $joinAlias, $parameterName))
            ->setParameter($parameterName, \is_array($value) ? $value : [$value]);
    }

    public static function getParameterProvider(): string
    {
        return IriConverterParameterProvider::class;
    }
}
