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

use Silverback\ApiComponentsBundle\Helper\OrphanedResource\OrphanedResourceNotificationResult;
use Silverback\ApiComponentsBundle\Helper\OrphanedResource\OrphanedResourceNotifier;
use Silverback\ApiComponentsBundle\MessageHandler\ScanOrphanedResourcesHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: self::NAME, aliases: [self::LEGACY_NAME])]
class ScanOrphanedCommand extends Command
{
    public const string NAME = 'silverback:api-components:scan-orphaned';
    public const string LEGACY_NAME = 'silverback:api-components:clean-orphaned';

    public function __construct(
        private readonly ScanOrphanedResourcesHandler $scanner,
        private readonly OrphanedResourceNotifier $notifier,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Scans for orphaned component groups, empty component positions and unused components, and stores the report the admin API returns. It never deletes anything.')
            ->setHelp('Prints the number of orphans of each kind; add -v to list their IRIs. When the result differs from the orphans at the last alert, the recipients configured under silverback_api_components.orphaned_resources.notify are emailed; --no-notify skips that. Orphans are deleted through the API: DELETE per IRI, or POST /_/orphaned_resources/delete.')
            ->addOption('notify', null, InputOption::VALUE_NEGATABLE, 'Email the configured recipients when the orphans have changed since the last alert', true);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $change = $this->scanner->scanAndCompare();
        $report = $change->report;
        $kinds = [
            'Component groups' => $report->componentGroups,
            'Component positions' => $report->componentPositions,
            'Components' => $report->components,
        ];
        foreach ($kinds as $heading => $iris) {
            $output->writeln(\sprintf('%s: %d', $heading, \count($iris)));
            if ($output->isVerbose()) {
                foreach ($iris as $iri) {
                    $output->writeln('  ' . $iri);
                }
            }
        }

        if ($input->getOption('notify')) {
            $result = $this->notifier->notify($change);
            $this->scanner->recordNotification($change, $result);
            $message = match ($result) {
                OrphanedResourceNotificationResult::Sent => 'The report has changed: a notification was sent.',
                OrphanedResourceNotificationResult::Unchanged => 'The report has not changed: no notification was sent.',
                OrphanedResourceNotificationResult::Failed => 'The report has changed, but the notification could not be sent. The error has been logged.',
                OrphanedResourceNotificationResult::NoRecipients => null,
            };
            if (null !== $message) {
                $output->writeln($message);
            }
        }

        return Command::SUCCESS;
    }
}
