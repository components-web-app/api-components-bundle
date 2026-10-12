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

use Liip\ImagineBundle\Imagine\Filter\FilterConfiguration;
use Silverback\ApiComponentsBundle\Helper\Uploadable\ImagineVariantGenerator;
use Silverback\ApiComponentsBundle\Imagine\PhpMemoryLimit;
use Silverback\ApiComponentsBundle\Model\Uploadable\ImagineVariantStatus;
use Silverback\ApiComponentsBundle\Utility\ResidentMemory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: self::NAME)]
final class ImagineGenerateCommand extends Command
{
    public const string NAME = 'silverback:api-components:imagine:generate';
    public const int INCOMPLETE = 3;

    public function __construct(
        private readonly ImagineVariantGenerator $generator,
        private readonly FilterConfiguration $filterConfiguration,
        private readonly ResidentMemory $residentMemory = new ResidentMemory(),
        private readonly string $vipsConfigClass = 'Jcupitt\\Vips\\Config',
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Generates every imagine filter variant of the uploaded images that is not stored yet, one image at a time, so a request only resolves variants. Stored variants and originals are not touched.')
            ->setHelp('Run it after a deploy that adds an imagine filter, then purge the HTTP cache so cached responses list the new variants. It can be re-run at any time: what is already stored is skipped. Exits 1 if any variant failed; a variant skipped over the memory budget is reported but does not fail the run. Exits 3 when --limit or --max-memory stopped it with variants still missing: run it again in a new process until it exits 0. libvips allocates outside PHP\'s memory_limit, so --max-memory checks the resident memory of the process. Add -v to list each variant.')
            ->addOption('filter', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only generate this imagine filter (repeatable)')
            ->addOption('class', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only walk this uploadable class or its subclasses (repeatable)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Count the variants that are missing without generating them')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Stop after this many variants and exit 3 if more are missing')
            ->addOption('max-memory', null, InputOption::VALUE_REQUIRED, 'Stop once the process\'s resident memory reaches this size (e.g. 768M) and exit 3 if more are missing; at least one variant is always generated');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $classes = $input->getOption('class');
        $uploadableClasses = $this->generator->uploadableClasses();
        foreach ($classes as $class) {
            if (!array_filter($uploadableClasses, static fn (string $uploadableClass): bool => is_a($uploadableClass, $class, true))) {
                $output->writeln(\sprintf('<error>"%s" is not an uploadable class.</error>', $class));

                return Command::INVALID;
            }
        }

        $filters = $input->getOption('filter');
        $configuredFilters = $this->filterConfiguration->all();
        foreach ($filters as $filter) {
            if (!\array_key_exists($filter, $configuredFilters)) {
                $output->writeln(\sprintf('<error>"%s" is not a configured imagine filter.</error>', $filter));

                return Command::INVALID;
            }
        }

        $limit = $input->getOption('limit');
        if (null !== $limit) {
            if (!preg_match('/^[1-9]\d*$/', $limit)) {
                $output->writeln('<error>--limit must be a positive whole number.</error>');

                return Command::INVALID;
            }
            $limit = (int) $limit;
        }
        $maxMemory = $input->getOption('max-memory');
        if (null !== $maxMemory) {
            $maxMemory = PhpMemoryLimit::parse($maxMemory);
            if (null === $maxMemory) {
                $output->writeln('<error>--max-memory must be a size such as 768M or 1G.</error>');

                return Command::INVALID;
            }
        }

        $dryRun = $input->getOption('dry-run');
        $attempted = 0;
        $stopReason = null;
        $proceed = null;
        if (!$dryRun) {
            $this->disableVipsCache();
            $proceed = function () use (&$attempted, &$stopReason, $limit, $maxMemory): bool {
                if ($attempted > 0) {
                    if (null !== $limit && $attempted >= $limit) {
                        $stopReason = \sprintf('Stopped after %d variant%s (--limit)', $attempted, 1 === $attempted ? '' : 's');

                        return false;
                    }
                    if (null !== $maxMemory && ($resident = $this->residentMemory->bytes()) >= $maxMemory) {
                        $stopReason = \sprintf('Stopped at %s resident memory (--max-memory %s) after %d variant%s', $this->mebibytes($resident), $this->mebibytes($maxMemory), $attempted, 1 === $attempted ? '' : 's');

                        return false;
                    }
                }
                ++$attempted;

                return true;
            };
        }

        $counts = array_fill_keys(array_map(static fn (ImagineVariantStatus $status): string => $status->name, ImagineVariantStatus::cases()), 0);
        foreach ($this->generator->generate($classes, $filters, $dryRun, $proceed) as $variant) {
            ++$counts[$variant->status->name];
            if ($output->isVerbose()) {
                $output->writeln(\sprintf('  %s: %s %s', $this->label($variant->status), $variant->filter, $variant->path));
            }
        }

        if ($dryRun) {
            $output->writeln(\sprintf('To generate: %d', $counts[ImagineVariantStatus::Pending->name]));
        } else {
            $output->writeln(\sprintf('Generated: %d', $counts[ImagineVariantStatus::Generated->name]));
        }
        $output->writeln(\sprintf('Already stored: %d', $counts[ImagineVariantStatus::Stored->name]));
        if (!$dryRun) {
            $output->writeln(\sprintf('Skipped over the memory budget: %d', $counts[ImagineVariantStatus::Skipped->name]));
        }
        $output->writeln(\sprintf('Failed: %d', $counts[ImagineVariantStatus::Failed->name]));
        $output->writeln(\sprintf('Original missing: %d', $counts[ImagineVariantStatus::OriginalMissing->name]));

        if (null !== $stopReason) {
            $output->writeln(\sprintf('<comment>%s; variants are still missing, so run the command again.</comment>', $stopReason));

            return self::INCOMPLETE;
        }

        return $counts[ImagineVariantStatus::Failed->name] ? Command::FAILURE : Command::SUCCESS;
    }

    private function disableVipsCache(): void
    {
        if (class_exists($this->vipsConfigClass)) {
            $this->vipsConfigClass::cacheSetMax(0);
        }
    }

    private function mebibytes(int $bytes): string
    {
        return \sprintf('%.1f MiB', $bytes / 1048576);
    }

    private function label(ImagineVariantStatus $status): string
    {
        return match ($status) {
            ImagineVariantStatus::Generated => 'generated',
            ImagineVariantStatus::Stored => 'stored',
            ImagineVariantStatus::Pending => 'missing',
            ImagineVariantStatus::Skipped => 'skipped',
            ImagineVariantStatus::Failed => 'failed',
            ImagineVariantStatus::OriginalMissing => 'original missing',
        };
    }
}
