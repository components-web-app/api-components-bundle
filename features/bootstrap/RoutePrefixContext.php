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
use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Session;
use Behat\MinkExtension\Context\MinkContext;
use FriendsOfBehat\SymfonyExtension\Driver\SymfonyDriver;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\DataCollector\HttpClientDataCollector;
use Symfony\Component\HttpKernel\KernelInterface;

final class RoutePrefixContext implements Context
{
    private const string SESSION = 'route_prefixed';

    /** @var array<string, KernelInterface> */
    private static array $kernels = [];

    private ?KernelInterface $kernel = null;
    private ?int $commandStatusCode = null;
    private string $commandOutput = '';
    private ?AbstractBrowser $mainClient = null;
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
        $this->mainClient?->restart();
        $this->mainClient = null;
    }

    /**
     * @Given the API routes are imported under the prefix :prefix
     */
    public function theApiRoutesAreImportedUnderThePrefix(string $prefix): void
    {
        $this->kernel = $this->getBootedKernel($prefix);

        $previousClient = $this->mainClient = $this->minkContext->getSession()->getDriver()->getClient();
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
        $container = $this->rebootOutsideAnHttpRequest();

        $route = new Route();
        $route->setPath($path)->setName($path);
        $container->get(TimestampedDataPersister::class)->persistTimestampedFields($route, true);
        $manager = $container->get('doctrine')->getManager();
        $manager->persist($route);
        $manager->flush();
        $manager->clear();

        $this->collectOutOfRequestHttpClientTraces($container);
    }

    /**
     * @When I run the HTTP cache purge command with:
     */
    public function iRunTheHttpCachePurgeCommandWith(TableNode $table): void
    {
        $container = $this->rebootOutsideAnHttpRequest();

        $input = [];
        foreach ($table->getHash() as ['option' => $option, 'value' => $value]) {
            $input[$option][] = $value;
        }

        $tester = new CommandTester((new Application($this->kernel))->find('silverback:api-components:purge-http-cache'));
        $this->commandStatusCode = $tester->execute($input);
        $this->commandOutput = $tester->getDisplay();

        $this->collectOutOfRequestHttpClientTraces($container);
    }

    /**
     * @Then the HTTP cache purge command should have exited with :code
     */
    public function theHttpCachePurgeCommandShouldHaveExitedWith(int $code): void
    {
        if ($code !== $this->commandStatusCode) {
            throw new \RuntimeException(\sprintf('The command exited with %s. Output: %s', var_export($this->commandStatusCode, true), $this->commandOutput));
        }
    }

    /**
     * @Then the HTTP cache purge command output should contain :text
     */
    public function theHttpCachePurgeCommandOutputShouldContain(string $text): void
    {
        if (!str_contains($this->commandOutput, $text)) {
            throw new \RuntimeException(\sprintf('The command output does not contain "%s". Output: %s', $text, $this->commandOutput));
        }
    }

    private function rebootOutsideAnHttpRequest(): ContainerInterface
    {
        if (!$this->kernel) {
            throw new \RuntimeException('Import the API routes under a prefix before acting outside an HTTP request.');
        }

        $this->kernel->shutdown();
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');

        if (null !== $container->get('request_stack')->getMainRequest()) {
            throw new \RuntimeException('A request is on the request stack, so this would not be outside an HTTP request.');
        }

        return $container;
    }

    private function collectOutOfRequestHttpClientTraces(ContainerInterface $container): void
    {
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
