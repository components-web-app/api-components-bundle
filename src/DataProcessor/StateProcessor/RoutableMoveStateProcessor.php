<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\DataProcessor\StateProcessor;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPage;
use Silverback\ApiComponentsBundle\Helper\Route\RouteCascadePlanner;
use Silverback\ApiComponentsBundle\Helper\Route\RouteMovePlanner;

/**
 * @implements ProcessorInterface<mixed, mixed>
 *
 * @author Daniel West <daniel@silverback.is>
 */
final readonly class RoutableMoveStateProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<mixed, mixed> $decorated
     */
    public function __construct(
        private ProcessorInterface $decorated,
        private ManagerRegistry $registry,
        private RouteMovePlanner $movePlanner,
        private RouteCascadePlanner $cascadePlanner,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (
            !$data instanceof AbstractPage
            || !$operation instanceof HttpOperation
            || !\in_array($operation->getMethod(), [HttpOperation::METHOD_PUT, HttpOperation::METHOD_PATCH], true)
        ) {
            return $this->decorated->process($data, $operation, $uriVariables, $context);
        }

        $entityManager = $this->registry->getManagerForClass($data::class);
        if (!$entityManager instanceof EntityManagerInterface) {
            return $this->decorated->process($data, $operation, $uriVariables, $context);
        }

        $original = $entityManager->getUnitOfWork()->getOriginalEntityData($data);
        $field = self::changedParentField($data, $original);
        if (null === $field) {
            return $this->decorated->process($data, $operation, $uriVariables, $context);
        }

        $originalParent = $original['parentPage'] ?? $original['parentPageData'] ?? null;
        $nodes = $this->movePlanner->plan($data, $originalParent instanceof AbstractPage ? $originalParent : null, $entityManager);
        $violations = $this->movePlanner->violations($nodes, $data, $field, $entityManager);
        if (\count($violations) > 0) {
            throw new ValidationException($violations);
        }

        $result = $this->decorated->process($data, $operation, $uriVariables, $context);

        if ([] !== $nodes) {
            $this->cascadePlanner->apply($nodes, $entityManager);
            $entityManager->flush();
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $original
     */
    private static function changedParentField(AbstractPage $page, array $original): ?string
    {
        if ([] === $original) {
            return null;
        }

        $parentPageChanged = ($original['parentPage'] ?? null) !== $page->getParentPage();
        $parentPageDataChanged = ($original['parentPageData'] ?? null) !== $page->getParentPageData();
        if ($parentPageDataChanged && (null !== $page->getParentPageData() || !$parentPageChanged)) {
            return 'parentPageData';
        }

        return $parentPageChanged ? 'parentPage' : null;
    }
}
