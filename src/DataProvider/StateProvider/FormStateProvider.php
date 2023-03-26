<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\DataProvider\StateProvider;

use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentsBundle\Entity\Component\Form;
use Silverback\ApiComponentsBundle\Form\Type\User\PasswordUpdateType;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class FormStateProvider implements ProviderInterface
{
    public function __construct(private readonly ProviderInterface $defaultProvider)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            return $this->defaultProvider->provide($operation->withProvider(CollectionProvider::class), $uriVariables, $context);
        }

        $id = $uriVariables['id'];
        if ('password_reset' === $id) {
            $dummyFormComponent = new Form();
            $dummyFormComponent->formType = PasswordUpdateType::class;
            $refObject = new \ReflectionObject($dummyFormComponent);
            $refProperty = $refObject->getProperty('id');
            $refProperty->setValue($dummyFormComponent, Uuid::uuid4());

            return $dummyFormComponent;
        }

        return $this->defaultProvider->provide($operation->withProvider(ItemProvider::class), $uriVariables, $context);
    }
}
