<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\EventListener\Api;

use Silverback\ApiComponentBundle\AnnotationReader\UploadableAnnotationReader;
use Silverback\ApiComponentBundle\Utility\ClassMetadataTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class UploadableEventListener
{
    use ClassMetadataTrait;

    private UploadableAnnotationReader $uploadableHelper;

    public function __construct(UploadableAnnotationReader $uploadableHelper)
    {
        $this->uploadableHelper = $uploadableHelper;
    }

    public function onPreWrite(ViewEvent $event): void
    {
        $request = $event->getRequest();
        $data = $request->attributes->get('data');
        if (
            empty($data) ||
            !$this->uploadableHelper->isConfigured($data) ||
            $request->isMethod(Request::METHOD_DELETE)
        ) {
            return;
        }

        foreach ($this->uploadableHelper->getConfiguredProperties($data) as $field) {
            if (null !== $field) {
                // todo Upload file to adapter
                // todo Set fileName on object
                // todo Set $field value to null for security/performance reasons
            }
        }
    }
}
