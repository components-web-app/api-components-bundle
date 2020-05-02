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

namespace Silverback\ApiComponentsBundle\Entity\Core;

use Silverback\ApiComponentsBundle\Entity\Utility\ComponentGroupsTrait;
use Silverback\ApiComponentsBundle\Entity\Utility\UiTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class PageTemplate extends AbstractPage
{
    use UiTrait;
    use ComponentGroupsTrait;

    public ?Layout $layout;

    public function __construct()
    {
        parent::__construct();
        $this->initComponentGroups();
    }

    public function __clone()
    {
        parent::__construct();
    }
}
