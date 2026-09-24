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
use Behat\Mink\Session;
use Behat\MinkExtension\Context\MinkContext;
use FriendsOfBehat\SymfonyExtension\Driver\SymfonyDriver;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\DataCollector\HttpClientDataCollector;
use Symfony\Component\HttpKernel\KernelInterface;

final class RoutePrefixContext implements Context
{
    private const string SESSION = 'route_prefixed';

    /** @var array<string, KernelInterface> */
    private static array $kernels = [];

    private ?KernelInterface $kernel = null;
    private MinkContext $minkContext;
    private ProfilerContext $profilerContext;
    private RestContext $restContext;

    public function __construct(private readonly KernelInterface $mainKernel)
    {
    }

    /**
     * @BeforeScenario
     */
    public function gatherContexts(BeforeScenarioScope $scope): void
    {
        $this->minkContext = $scope->getEnvironment()->getContext(MinkContext::class);
        $this->profilerContext = $scope->getEnvironment()->getContext(ProfilerContext::class);
        $this->restContext = $scope->getEnvironment()->getContext(RestContext::class);
    }

    /**
     * @AfterScenario
     */
    public function shutdownPrefixedKernel(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    /**
     * @Given the API routes are imported under the prefix :prefix
     */
    public function theApiRoutesAreImportedUnderThePrefix(string $prefix): void
    {
        $this->kernel = $this->getBootedKernel($prefix);

        $previousClient = $this->minkContext->getSession()->getDriver()->getClient();
        $driver = new SymfonyDriver($this->kernel, $this->minkContext->getMinkParameter('base_url'));
        foreach ($previousClient->getCookieJar()->all() as $cookie) {
            $driver->getClient()->getCookieJar()->set($cookie);
        }

        $mink = $this->minkContext->getMink();
        $mink->registerSession(self::SESSION, new Session($driver));
        $mink->setDefaultSessionName(self::SESSION);
        $this->restContext->resourceIriPrefix = $prefix;
    }

    /**
     * @When a Route with the path :path is written outside an HTTP request
     */
    public function aRouteIsWrittenOutsideAnHttpRequest(string $path): void
    {
        if (!$this->kernel) {
            throw new \RuntimeException('Import the API routes under a prefix before writing outside an HTTP request.');
        }

        $this->kernel->shutdown();
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');

        if (null !== $container->get('request_stack')->getMainRequest()) {
            throw new \RuntimeException('A request is on the request stack, so this write would not be outside an HTTP request.');
        }

        $route = new Route();
        $route->setPath($path)->setName($path);
        $container->get(TimestampedDataPersister::class)->persistTimestampedFields($route, true);
        $manager = $container->get('doctrine')->getManager();
        $manager->persist($route);
        $manager->flush();
        $manager->clear();

        /** @var HttpClientDataCollector $collector */
        $collector = $container->get('data_collector.http_client');
        $collector->lateCollect();
        $this->profilerContext->useOutOfRequestHttpClientCollector($collector);
    }

    private function getBootedKernel(string $prefix): KernelInterface
    {
        if (!isset(self::$kernels[$prefix])) {
            $kernel = new \AppKernel($this->mainKernel->getEnvironment(), $this->mainKernel->isDebug(), $prefix);
            (new Filesystem())->remove($kernel->getCacheDir());
            self::$kernels[$prefix] = $kernel;
        }

        self::$kernels[$prefix]->boot();

        return self::$kernels[$prefix];
    }
}
