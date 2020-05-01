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

namespace Silverback\ApiComponentsBundle\EventListener\Imagine;

use Silverback\ApiComponentsBundle\Event\ImagineRemoveEvent;
use Silverback\ApiComponentsBundle\Event\ImagineStoreEvent;
use Silverback\ApiComponentsBundle\Uploadable\UploadableHelper;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ImagineEventListener
{
    private UploadableHelper $uploadableHelper;

    public function __construct(UploadableHelper $uploadableHelper)
    {
        $this->uploadableHelper = $uploadableHelper;
    }

    public function onStore(ImagineStoreEvent $event)
    {
        // dump('I have the store event!');
    }

    public function onRemove(ImagineRemoveEvent $event)
    {
        // dump('I have the remove event!');
    }
}
