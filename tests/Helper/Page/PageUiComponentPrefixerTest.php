<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\Page;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Doctrine\UuidType;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentsBundle\Command\PrefixPageUiComponentsCommand;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Helper\Page\PageUiComponentPrefixer;
use Symfony\Component\Console\Tester\CommandTester;

class PageUiComponentPrefixerTest extends TestCase
{
    private EntityManager $entityManager;
    private string $table;
    private string $column;

    protected function setUp(): void
    {
        if (!Type::hasType('uuid')) {
            Type::addType('uuid', UuidType::class);
        }
        $configuration = ORMSetup::createAttributeMetadataConfig([__DIR__ . '/../../../src/Entity/Core'], true);
        $configuration->enableNativeLazyObjects(true);
        $this->entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration), $configuration);
        $classMetadata = $this->entityManager->getClassMetadata(Page::class);
        $this->table = $classMetadata->getTableName();
        $this->column = $classMetadata->getColumnName('uiComponent');
        $this->entityManager->getConnection()->executeStatement(\sprintf(
            'CREATE TABLE %s (%s CHAR(36) NOT NULL, %s VARCHAR(255) DEFAULT NULL, PRIMARY KEY (%s))',
            $this->table,
            $classMetadata->getColumnName('id'),
            $this->column,
            $classMetadata->getColumnName('id')
        ));
    }

    public function test_short_names_gain_the_prefix_and_everything_else_is_left_alone(): void
    {
        $this->insertRows(['PrimaryPageTemplate', 'CwaPagePrimaryPageTemplate', 'Secondary', null, '', 'cwaPageLower']);

        self::assertSame(3, (new PageUiComponentPrefixer($this->entityManager))->prefix());
        self::assertSame(['', 'CwaPagePrimaryPageTemplate', 'CwaPagePrimaryPageTemplate', 'CwaPageSecondary', 'CwaPagecwaPageLower', 'NULL'], $this->rows());
    }

    public function test_a_second_run_changes_nothing(): void
    {
        $this->insertRows(['PrimaryPageTemplate', 'CwaPageSecondary']);
        $prefixer = new PageUiComponentPrefixer($this->entityManager);
        $prefixer->prefix();

        self::assertSame(0, $prefixer->prefix());
        self::assertSame(['CwaPagePrimaryPageTemplate', 'CwaPageSecondary'], $this->rows());
    }

    public function test_an_empty_table_needs_nothing(): void
    {
        self::assertSame(0, (new PageUiComponentPrefixer($this->entityManager))->prefix());
    }

    public function test_the_command_reports_what_it_changed(): void
    {
        $this->insertRows(['PrimaryPageTemplate', 'Secondary', 'CwaPageThird']);
        $tester = new CommandTester(new PrefixPageUiComponentsCommand(new PageUiComponentPrefixer($this->entityManager)));

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('Prefixed the uiComponent of 2 pages with CwaPage.', $tester->getDisplay());

        $this->insertRows(['Fourth']);
        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('Prefixed the uiComponent of 1 page with CwaPage.', $tester->getDisplay());

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('Prefixed the uiComponent of 0 pages with CwaPage.', $tester->getDisplay());
    }

    /**
     * @param list<?string> $uiComponents
     */
    private function insertRows(array $uiComponents): void
    {
        foreach ($uiComponents as $uiComponent) {
            $this->entityManager->getConnection()->insert($this->table, [
                'id' => Uuid::uuid4()->toString(),
                $this->column => $uiComponent,
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function rows(): array
    {
        return array_values($this->entityManager->getConnection()->fetchFirstColumn(
            \sprintf("SELECT COALESCE(%s, 'NULL') FROM %s ORDER BY 1", $this->column, $this->table)
        ));
    }
}
