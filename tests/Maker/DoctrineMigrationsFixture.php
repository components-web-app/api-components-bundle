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
use Doctrine\DBAL\DriverManager;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ExistingConfiguration;
use Doctrine\Migrations\DependencyFactory;
use Psr\Log\NullLogger;

final class DoctrineMigrationsFixture
{
    public readonly string $namespace;
    public readonly string $directory;
    public readonly DependencyFactory $dependencyFactory;

    /**
     * @param array<string, string> $extraDirectories
     */
    public function __construct(?Connection $connection = null, array $extraDirectories = [])
    {
        $id = bin2hex(random_bytes(8));
        $this->namespace = 'Silverback\\ApiComponentsBundle\\Tests\\Maker\\GeneratedMigrations\\M' . $id;
        $this->directory = sys_get_temp_dir() . '/cwa-rename-migrations-' . $id;
        mkdir($this->directory);

        $configuration = new Configuration();
        $configuration->addMigrationsDirectory($this->namespace, $this->directory);
        foreach ($extraDirectories as $namespace => $directory) {
            $configuration->addMigrationsDirectory($namespace, $directory);
        }

        $this->dependencyFactory = DependencyFactory::fromConnection(
            new ExistingConfiguration($configuration),
            new ExistingConnection($connection ?? DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true])),
        );
    }

    public function __destruct()
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    /**
     * @return list<string>
     */
    public function generatedFiles(): array
    {
        return glob($this->directory . '/*.php') ?: [];
    }

    public function hasGenerated(): bool
    {
        return [] !== $this->generatedFiles();
    }

    public function generatedPath(): string
    {
        $files = $this->generatedFiles();
        if (1 !== \count($files)) {
            throw new \LogicException(\sprintf('Expected exactly one generated migration, found %d.', \count($files)));
        }

        return $files[0];
    }

    public function generatedSource(): string
    {
        return (string) file_get_contents($this->generatedPath());
    }

    public function createMigration(Connection $connection): AbstractMigration
    {
        $path = $this->generatedPath();
        $fqcn = $this->namespace . '\\' . basename($path, '.php');
        if (!class_exists($fqcn, false)) {
            require $path;
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
