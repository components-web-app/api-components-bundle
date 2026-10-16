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
use Silverback\ApiComponentsBundle\Fixture\Asset\FixtureAssetManifest;

abstract class AbstractCwaScaffold implements FixtureInterface
{
    public function __construct(private readonly CwaFixtureBuilder $cwa)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $class = new \ReflectionClass($this);
        $cwa = $this->cwa->withManager($manager)->withAssetManifest(FixtureAssetManifest::pathFor(\dirname((string) $class->getFileName()), $class->getShortName()));
        $this->build($cwa);
        $cwa->flush();
        $cwa->reportSummary();
    }

    abstract public function build(CwaFixtureBuilder $cwa): void;
}
