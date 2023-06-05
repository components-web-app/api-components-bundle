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

use ApiPlatform\Metadata\Operation;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Daniel West <daniel@silverback.is>
 */
trait ApiEventListenerTrait
{
    #[ArrayShape(['data' => 'mixed', 'class' => 'string', 'operation' => Operation::class])]
    private function getAttributes(Request $request): array
    {
        $operation = $request->attributes->get('_api_operation');
        $data = $request->attributes->get('data');
        $class = null;
        if (\is_object($data)) {
            $class = $data::class;
        } else {
            $normalizationContext = $request->attributes->get('_api_normalization_context');
            if ($normalizationContext && isset($normalizationContext['resource_class'])) {
                $class = $normalizationContext['resource_class'];
            }
        }

        return [
            'data' => $data,
            'class' => $class,
            'operation' => $operation,
        ];
    }
}
