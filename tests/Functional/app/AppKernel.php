<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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

    public function __construct(string $environment, bool $debug, private readonly string $routePrefix = '')
    {
        parent::__construct($environment, $debug);

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
        return $this->getVarDir() . '/cache/' . $this->environment . self::symfonyListenersSuffix() . $this->getRoutePrefixSuffix();
    }

    public static function useSymfonyListeners(): ?bool
    {
        $value = getenv('API_PLATFORM_USE_SYMFONY_LISTENERS');
        if (false === $value || '' === $value) {
            return null;
        }

        return filter_var($value, \FILTER_VALIDATE_BOOL);
    }

    private static function symfonyListenersSuffix(): string
    {
        return match (self::useSymfonyListeners()) {
            null => '',
            true => '_listeners',
            false => '_controller',
        };
    }

    public static function shardSuffix(): string
    {
        $shard = preg_replace('/[^A-Za-z0-9]+/', '', (string) getenv('BEHAT_SHARD'));

        return '' === $shard ? '' : '/shard-' . $shard;
    }

    private function getVarDir(): string
    {
        return $this->getProjectDir() . '/var' . self::shardSuffix();
    }

    protected function getContainerClass(): string
    {
        return parent::getContainerClass() . $this->getRoutePrefixSuffix();
    }

    private function getRoutePrefixSuffix(): string
    {
        return '' === $this->routePrefix ? '' : '_' . preg_replace('/[^A-Za-z0-9]+/', '', $this->routePrefix);
    }

    public function getLogDir(): string
    {
        return $this->getVarDir() . '/log';
    }

    public function getProjectDir(): string
    {
        return parent::getProjectDir() . '/tests/Functional/app';
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import($this->getProjectDir() . '/../../../src/Resources/config/routing/*' . self::CONFIG_EXTS)->prefix($this->routePrefix);

        $configDir = $this->getProjectDir() . '/config';
        if (is_dir($configDir . '/routes/')) {
            $routes->import($configDir . '/routes/*' . self::CONFIG_EXTS)->prefix($this->routePrefix);
        }
        if (is_dir($configDir . '/routes/' . $this->environment)) {
            $routes->import($configDir . '/routes/' . $this->environment . '/**/*' . self::CONFIG_EXTS)->prefix($this->routePrefix);
        }
        $routes->import($configDir . '/routes' . self::CONFIG_EXTS)->prefix($this->routePrefix);
    }

    protected function configureContainer(ContainerConfigurator $container, LoaderInterface $loader, ContainerBuilder $builder): void
    {
        $confDir = $this->getProjectDir() . '/config';
        $container->import($confDir . '/packages/*' . self::CONFIG_EXTS);
        if (is_dir($confDir . '/packages/' . $this->environment)) {
            $container->import($confDir . '/packages/' . $this->environment . '/**/*' . self::CONFIG_EXTS);
        }

        $container->import($confDir . '/services' . self::CONFIG_EXTS);
        $container->import($confDir . '/services_' . $this->environment . self::CONFIG_EXTS);

        if (null !== $useSymfonyListeners = self::useSymfonyListeners()) {
            $container->extension('api_platform', ['use_symfony_listeners' => $useSymfonyListeners]);
        }
    }
}
