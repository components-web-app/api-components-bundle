<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Maker;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\AbstractMigration;
use Psr\Log\NullLogger;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\Util\ClassNameDetails;

final class RenderingMigrationGenerator extends Generator
{
    private const NAMESPACE = 'Silverback\\ApiComponentsBundle\\Tests\\Maker\\Generated';

    private string $className;
    private ?string $source = null;
    private array $variables = [];

    public function __construct()
    {
        $this->className = 'RenameComponentMigration' . bin2hex(random_bytes(8));
    }

    public function createClassNameDetails(string $name, string $namespacePrefix, string $suffix = '', string $validationErrorMessage = ''): ClassNameDetails
    {
        return new ClassNameDetails(self::NAMESPACE . '\\' . $this->className, $namespacePrefix);
    }

    public function generateClass(string $className, string $templateName, array $variables = []): string
    {
        $this->variables = $variables;
        $variables['namespace'] = self::NAMESPACE;
        $variables['class_name'] = $this->className;

        $this->source = (static function (string $__template, array $__variables): string {
            extract($__variables);
            ob_start();
            include $__template;

            return (string) ob_get_clean();
        })($templateName, $variables);

        return 'migrations/' . $this->className . '.php';
    }

    public function writeChanges(): void
    {
    }

    public function hasGenerated(): bool
    {
        return null !== $this->source;
    }

    public function getSource(): string
    {
        return $this->source ?? throw new \LogicException('No migration has been generated.');
    }

    public function getVariables(): array
    {
        return $this->variables;
    }

    public function createMigration(Connection $connection): AbstractMigration
    {
        $fqcn = self::NAMESPACE . '\\' . $this->className;
        if (!class_exists($fqcn, false)) {
            eval(substr($this->getSource(), \strlen('<?php')));
        }

        return new $fqcn($connection, new NullLogger());
    }

    public function migrate(Connection $connection, string $direction): void
    {
        $migration = $this->createMigration($connection);
        $migration->{$direction}($connection->createSchemaManager()->introspectSchema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement(), $query->getParameters());
        }
    }
}
