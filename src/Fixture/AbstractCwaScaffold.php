<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Fixture;

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Persistence\ObjectManager;

abstract class AbstractCwaScaffold implements FixtureInterface
{
    public function __construct(private readonly CwaFixtureBuilder $cwa)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $cwa = $this->cwa->withManager($manager);
        $this->build($cwa);
        $cwa->flush();
    }

    abstract public function build(CwaFixtureBuilder $cwa): void;
}
