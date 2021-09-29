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

use Silverback\ApiComponentsBundle\AnnotationReader\UploadableAnnotationReader;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class UploadableEventListener
{
    private UploadableAnnotationReader $uploadableAnnotationReader;
    private UploadableFileManager $uploadableFileManager;

    public function __construct(UploadableAnnotationReader $uploadableAnnotationReader, UploadableFileManager $uploadableFileManager)
    {
        $this->uploadableAnnotationReader = $uploadableAnnotationReader;
        $this->uploadableFileManager = $uploadableFileManager;
    }

    public function onPreWrite(ViewEvent $event): void
    {
        $request = $event->getRequest();
        $data = $request->attributes->get('data');
        if (
            empty($data) ||
            !$this->uploadableAnnotationReader->isConfigured($data) ||
            $request->isMethod(Request::METHOD_GET)
        ) {
            return;
        }
        if ($request->isMethod(Request::METHOD_DELETE)) {
            $this->uploadableFileManager->deleteFiles($data);

            return;
        }

        $this->uploadableFileManager->persistFiles($data);
    }

    public function onPostWrite(ViewEvent $event): void
    {
        $request = $event->getRequest();
        $data = $request->attributes->get('data');
        if (
            empty($data) ||
            !$this->uploadableAnnotationReader->isConfigured($data) ||
            $request->isMethod(Request::METHOD_GET) ||
            $request->isMethod(Request::METHOD_DELETE)
        ) {
            return;
        }

        $this->uploadableFileManager->storeFilesMetadata($data);
    }
}
