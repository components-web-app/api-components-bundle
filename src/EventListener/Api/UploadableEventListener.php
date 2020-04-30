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
use Silverback\ApiComponentBundle\Uploadable\UploadableHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class UploadableEventListener
{
    private UploadableAnnotationReader $uploadableAnnotationReader;
    private UploadableHelper $uploadableHelper;

    public function __construct(UploadableAnnotationReader $uploadableAnnotationReader, UploadableHelper $uploadableHelper)
    {
        $this->uploadableAnnotationReader = $uploadableAnnotationReader;
        $this->uploadableHelper = $uploadableHelper;
    }

    public function onPreWrite(ViewEvent $event): void
    {
        $request = $event->getRequest();
        $data = $request->attributes->get('data');
        if (
            empty($data) ||
            !$this->uploadableAnnotationReader->isConfigured($data) ||
            $request->isMethod(Request::METHOD_DELETE)
        ) {
            return;
        }
        $this->uploadableHelper->persistFiles($data);
    }
}
