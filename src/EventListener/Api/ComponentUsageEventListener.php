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
 * If the usage endpoint is used for a component, this will override the output with usage metadata.
 *
 * @author Daniel West <daniel@silverback.is>
 */
class ComponentUsageEventListener
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
        $operationName = $request->attributes->get('_api_operation_name');

        if (
            empty($data)
            || !$data instanceof ComponentInterface
            || !str_ends_with($operationName, '_get_usage')
        ) {
            return;
        }

        $request->attributes->set('data', $this->metadataFactory->create($data));
    }
}
