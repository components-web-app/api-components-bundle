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

use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Daniel West <daniel@silverback.is>
 */
trait ApiEventListenerTrait
{
    #[ArrayShape(['data' => 'mixed', 'class' => 'string'])]
    private function getAttributes(Request $request): array
    {
        $data = $request->attributes->get('data');
        $normalizationContext = $request->attributes->get('_api_normalization_context');
        $class = null;
        if ($normalizationContext) {
            $class = $normalizationContext['resource_class'];
        }
        if (!$class && $data) {
            $class = \get_class($data);
        }

        return [
            'data' => $data,
            'class' => $class,
        ];
    }
}
