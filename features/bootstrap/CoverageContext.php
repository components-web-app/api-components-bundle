<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Features\Bootstrap;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Driver\Selector;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\CodeCoverage\Report\Clover;
use SebastianBergmann\FileIterator\Facade as FileIteratorFacade;

/**
 * Behat coverage.
 *
 * Writes a Clover XML report directly. The report is consumed as-is by Codecov -
 * there is deliberately no intermediate `.cov` + `phpcov merge` step: current
 * phpcov releases only read the newer php-code-coverage serialization format,
 * which the version this project installs cannot write.
 *
 * @author eliecharra
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @copyright Adapted from https://gist.github.com/eliecharra/9c8b3ba57998b50e14a6
 */
final class CoverageContext implements Context
{
    private const DEFAULT_TARGET = '/build/logs/behat/clover.xml';

    private static ?CodeCoverage $coverage = null;

    /**
     * @BeforeSuite
     */
    public static function setup(): void
    {
        $projectDir = self::projectDir();

        $filter = new Filter();
        // Filter::includeFile() takes a single file - a directory registers a path that matches
        // nothing, so every file would be excluded and no coverage recorded at all.
        $filter->includeFiles(
            (new FileIteratorFacade())->getFilesAsArray(
                $projectDir . '/src',
                '.php',
                '',
                [$projectDir . '/src/Resources/config']
            )
        );

        self::$coverage = new CodeCoverage(
            (new Selector())->forLineCoverage($filter),
            $filter
        );
    }

    /**
     * @AfterSuite
     */
    public static function teardown(): void
    {
        if (null === self::$coverage) {
            return;
        }

        (new Clover())->process(self::$coverage, self::target());
    }

    /**
     * @BeforeScenario
     */
    public function before(BeforeScenarioScope $scope): void
    {
        self::$coverage?->start("{$scope->getFeature()->getTitle()}::{$scope->getScenario()->getTitle()}");
    }

    /**
     * @AfterScenario
     */
    public function after(): void
    {
        self::$coverage?->stop();
    }

    private static function target(): string
    {
        $target = getenv('BEHAT_COVERAGE_CLOVER');
        if (\is_string($target) && '' !== $target) {
            return $target;
        }

        return self::projectDir() . self::DEFAULT_TARGET;
    }

    private static function projectDir(): string
    {
        return \dirname(__DIR__, 2);
    }
}
