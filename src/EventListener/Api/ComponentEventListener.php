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

namespace Silverback\ApiComponentsBundle\EventListener\Api;

use Silverback\ApiComponentsBundle\Entity\Core\ComponentInterface;
use Silverback\ApiComponentsBundle\Metadata\Factory\ComponentUsageMetadataFactory;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ComponentEventListener
{
    private ComponentUsageMetadataFactory $metadataFactory;

    public function __construct(ComponentUsageMetadataFactory $metadataFactory)
    {
        $this->metadataFactory = $metadataFactory;
    }

    public function onPostRead(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $data = $request->attributes->get('data');
        $operationName = $request->attributes->get('_api_item_operation_name');
        if (
            empty($data) ||
            !$data instanceof ComponentInterface ||
            'get_usage' !== $operationName
        ) {
            return;
        }

        $request->attributes->set('data', $this->metadataFactory->create($data));
    }
}
