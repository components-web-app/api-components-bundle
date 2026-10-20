<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Command;

use Silverback\ApiComponentsBundle\HttpCache\Warm\HttpCacheWarmer;
use Silverback\ApiComponentsBundle\HttpCache\Warm\HttpCacheWarmResult;
use Silverback\ApiComponentsBundle\HttpCache\Warm\ModuleRequestHeaders;
use Silverback\ApiComponentsBundle\HttpCache\Warm\PublicRoutePaths;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'silverback:api-components:warm-http-cache', description: 'Requests the API responses server-side rendering fetches for the public routes, anonymously and with its headers, so the HTTP cache stores them under the same keys')]
class WarmHttpCacheCommand extends Command
{
    private const int SLOWEST = 5;

    public function __construct(
        private readonly PublicRoutePaths $publicRoutePaths,
        private readonly HttpCacheWarmer $httpCacheWarmer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('base-url', null, InputOption::VALUE_REQUIRED, 'The URL of the HTTP cache in front of the API', 'http://localhost')
            ->addOption('path', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Warm only the route at this path instead of every public route, e.g. /about-us (repeatable)')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Warm at most this many routes, shortest paths first')
            ->addOption('concurrency', null, InputOption::VALUE_REQUIRED, 'The most requests in flight at once', '2')
            ->addOption('max-time', null, InputOption::VALUE_REQUIRED, 'Stop starting requests after this many seconds, and still exit 0')
            ->addOption('timeout', null, InputOption::VALUE_REQUIRED, 'The most seconds one request may take', '30')
            ->addOption('accept-encoding', null, InputOption::VALUE_REQUIRED, 'The Accept-Encoding to send, or "none"', ModuleRequestHeaders::ACCEPT_ENCODING)
            ->addOption('requests-per-connection', null, InputOption::VALUE_REQUIRED, 'Send "Connection: close" on every Nth request, so a new connection is opened about every N requests and a Kubernetes Service spreads them across its pods; 1 opens a new connection for every request. By default connections are kept alive');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $baseUrl = $input->getOption('base-url');
        $paths = $input->getOption('path');
        $limit = $input->getOption('limit');
        $concurrency = $input->getOption('concurrency');
        $maxTime = $input->getOption('max-time');
        $timeout = $input->getOption('timeout');
        $acceptEncoding = $input->getOption('accept-encoding');
        $requestsPerConnection = $input->getOption('requests-per-connection');
        $acceptEncoding = 'none' === strtolower($acceptEncoding) ? null : $acceptEncoding;

        $errors = [];
        if (!preg_match('{\Ahttps?://[^/]}i', $baseUrl)) {
            $errors[] = '--base-url must be an http or https URL';
        }
        foreach ($paths as $path) {
            if (!str_starts_with($path, '/')) {
                $errors[] = \sprintf('A path must start with "/": "%s"', $path);
            }
        }
        if (!$this->isWholeNumber($concurrency)) {
            $errors[] = '--concurrency must be a whole number of at least 1';
        }
        if (null !== $limit && !$this->isWholeNumber($limit)) {
            $errors[] = '--limit must be a whole number of at least 1';
        }
        if (null !== $requestsPerConnection && !$this->isWholeNumber($requestsPerConnection)) {
            $errors[] = '--requests-per-connection must be a whole number of at least 1';
        }
        if (null !== $maxTime && !$this->isPositiveNumber($maxTime)) {
            $errors[] = '--max-time must be a number of seconds greater than 0';
        }
        if (!$this->isPositiveNumber($timeout)) {
            $errors[] = '--timeout must be a number of seconds greater than 0';
        }
        if (!$this->httpCacheWarmer->canDecode($acceptEncoding)) {
            $errors[] = '--accept-encoding asks for br, but the brotli PHP extension is not installed, so the warm could not follow a route to its manifest and components';
        }
        if ($errors) {
            foreach ($errors as $error) {
                $output->writeln(\sprintf('<error>%s</error>', $error));
            }

            return Command::INVALID;
        }

        $limit = null === $limit ? null : (int) $limit;
        $paths = [] === $paths ? $this->publicRoutePaths->find($limit) : \array_slice(array_values(array_unique($paths)), 0, $limit);
        if ([] === $paths) {
            $output->writeln('There is no public route to warm');

            return Command::SUCCESS;
        }

        $result = $this->httpCacheWarmer->warm(
            $paths,
            $baseUrl,
            (int) $concurrency,
            null === $maxTime ? null : (float) $maxTime,
            (float) $timeout,
            $acceptEncoding,
            null === $requestsPerConnection ? null : (int) $requestsPerConnection,
        );

        $this->writeSummary($output, \count($paths), $result, $maxTime);

        return Command::SUCCESS;
    }

    private function writeSummary(OutputInterface $output, int $routes, HttpCacheWarmResult $result, ?string $maxTime): void
    {
        $output->writeln(\sprintf(
            'Warmed %d %s with %d requests: %d hits, %d misses, %d failed',
            $routes,
            1 === $routes ? 'route' : 'routes',
            $result->countRequests(),
            $result->countHits(),
            $result->countMisses(),
            $result->countFailed(),
        ));
        if ($result->isStopped()) {
            $output->writeln(\sprintf('Stopped after the maximum time of %s seconds, before every route was warmed', $maxTime));
        }

        $slowest = $result->getSlowest(self::SLOWEST);
        if ($slowest) {
            $output->writeln('Slowest:');
            foreach ($slowest as $request) {
                $output->writeln(\sprintf('  %.3fs  %d  %s', $request->duration, $request->status, $request->url));
            }
        }

        if ($output->isVerbose() && $result->countFailed()) {
            $output->writeln('Failed:');
            foreach ($result->getRequests() as $request) {
                if ($request->isFailed()) {
                    $output->writeln(\sprintf('  %d  %s%s', $request->status, $request->url, null === $request->error ? '' : '  ' . $request->error));
                }
            }
        }
    }

    private function isWholeNumber(mixed $value): bool
    {
        return \is_string($value) && ctype_digit($value) && (int) $value >= 1;
    }

    private function isPositiveNumber(mixed $value): bool
    {
        return \is_string($value) && is_numeric($value) && (float) $value > 0;
    }
}
