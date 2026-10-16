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
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;

#[ORM\Entity]
#[ORM\Table(name: 'command_fixture_constructor_default_component')]
class ConstructorDefaultComponent extends AbstractComponent
{
    #[ORM\Column]
    public string $tone = 'plain';

    public function __construct()
    {
        parent::__construct();
        $this->tone = 'loud';
    }
}
