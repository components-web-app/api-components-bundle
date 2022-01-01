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

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * AppKernel for tests.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class AppKernel extends Kernel
{
    use MicroKernelTrait;

    private const CONFIG_EXTS = '.{php,xml,yaml,yml}';

    public function __construct(string $environment, bool $debug)
    {
        parent::__construct($environment, $debug);

        // patch for Behat/symfony2-extension not supporting %env(APP_ENV)%
        $this->environment = $_SERVER['APP_ENV'] ?? $environment;
    }

    public function registerBundles(): Iterator
    {
        $contents = require $this->getProjectDir() . '/config/bundles.php';
        foreach ($contents as $class => $envs) {
            if (isset($envs['all']) || isset($envs[$this->environment])) {
                yield new $class();
            }
        }
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir() . '/var/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir() . '/var/log';
    }

    public function getProjectDir(): string
    {
        return parent::getProjectDir() . '/tests/Functional/app';
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        // Import routes that should be included with flex / manually
        $routes->import($this->getProjectDir() . '/../../../src/Resources/config/routing/*' . self::CONFIG_EXTS);

        $configDir = $this->getProjectDir() . '/config';
        if (is_dir($configDir . '/routes/')) {
            $routes->import($configDir . '/routes/*' . self::CONFIG_EXTS);
        }
        if (is_dir($configDir . '/routes/' . $this->environment)) {
            $routes->import($configDir . '/routes/' . $this->environment . '/**/*' . self::CONFIG_EXTS);
        }
        $routes->import($configDir . '/routes' . self::CONFIG_EXTS);
    }

    protected function configureContainer(ContainerConfigurator $container, LoaderInterface $loader, ContainerBuilder $builder): void
    {
        // $container->setParameter('container.autowiring.strict_mode', true);
        // $container->setParameter('container.dumper.inline_class_loader', true);

        $confDir = $this->getProjectDir() . '/config';
        $container->import($confDir . '/packages/*' . self::CONFIG_EXTS);
        if (is_dir($confDir . '/packages/' . $this->environment)) {
            $container->import($confDir . '/packages/' . $this->environment . '/**/*' . self::CONFIG_EXTS);
        }

        $container->import($confDir . '/services' . self::CONFIG_EXTS);
        $container->import($confDir . '/services_' . $this->environment . self::CONFIG_EXTS);
    }
}
