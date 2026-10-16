<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Command\Fixture;

use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;

#[ORM\Entity]
#[ORM\Table(name: 'command_fixture_page_data_with_filters')]
class PageDataWithFilters extends AbstractPageData
{
    #[ORM\Column(type: 'json', nullable: true)]
    public ?array $filters = null;
}
