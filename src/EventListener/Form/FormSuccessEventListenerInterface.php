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

namespace Silverback\ApiComponentsBundle\EventListener\Form;

use Silverback\ApiComponentsBundle\Event\FormSuccessEvent;

interface FormSuccessEventListenerInterface
{
    public function __invoke(FormSuccessEvent $event): void;
}
