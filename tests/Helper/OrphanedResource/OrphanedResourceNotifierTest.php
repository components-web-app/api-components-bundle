<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\OrphanedResource;

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\ApiResource\OrphanedResourceReport;
use Silverback\ApiComponentsBundle\Exception\MailerTransportException;
use Silverback\ApiComponentsBundle\Factory\OrphanedResource\OrphanedResourcesChangedEmailFactory;
use Silverback\ApiComponentsBundle\Helper\OrphanedResource\OrphanedResourceNotificationResult;
use Silverback\ApiComponentsBundle\Helper\OrphanedResource\OrphanedResourceNotifier;
use Silverback\ApiComponentsBundle\Helper\OrphanedResource\OrphanedResourceReportChange;
use Silverback\ApiComponentsBundle\Helper\RefererUrlResolver;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class OrphanedResourceNotifierTest extends TestCase
{
    private const array RECIPIENTS = ['admin@example.com', 'ops@example.com'];

    private TestHandler $logs;

    protected function setUp(): void
    {
        $this->logs = new TestHandler();
    }

    public function test_nothing_is_sent_without_recipients(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        self::assertSame(OrphanedResourceNotificationResult::NoRecipients, $this->notifier($mailer, [])->notify($this->changed()));
    }

    public function test_nothing_is_sent_when_the_recipients_are_blank(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        self::assertSame(OrphanedResourceNotificationResult::NoRecipients, $this->notifier($mailer, ' , ')->notify($this->changed()));
        self::assertSame(OrphanedResourceNotificationResult::NoRecipients, $this->notifier($mailer, '')->notify($this->changed()));
        self::assertSame(OrphanedResourceNotificationResult::NoRecipients, $this->notifier($mailer, ['', ' '])->notify($this->changed()));
    }

    public function test_recipients_may_be_one_comma_separated_string(): void
    {
        $mailer = $this->recordingMailer();

        $this->notifier($mailer, ' admin@example.com, ops@example.com ,')->notify($this->changed());

        self::assertSame(self::RECIPIENTS, array_map(static fn ($address) => $address->getAddress(), $mailer->messages[0]->getTo()));
    }

    public function test_recipients_may_mix_bare_addresses_and_named_addresses(): void
    {
        $mailer = $this->recordingMailer();

        $result = $this->notifier($mailer, ['admin@example.com', 'My Website <website@website.com>'])->notify($this->changed());

        self::assertSame(OrphanedResourceNotificationResult::Sent, $result);
        self::assertSame(
            [['admin@example.com', ''], ['website@website.com', 'My Website']],
            array_map(static fn ($address) => [$address->getAddress(), $address->getName()], $mailer->messages[0]->getTo())
        );
    }

    public function test_a_comma_separated_string_may_mix_bare_addresses_and_named_addresses_with_a_comma_in_a_quoted_name(): void
    {
        $mailer = $this->recordingMailer();

        $this->notifier($mailer, 'admin@example.com, My Website <website@website.com>, "Smith, Jane" <jane@example.com>')->notify($this->changed());

        self::assertSame(
            [['admin@example.com', ''], ['website@website.com', 'My Website'], ['jane@example.com', 'Smith, Jane']],
            array_map(static fn ($address) => [$address->getAddress(), $address->getName()], $mailer->messages[0]->getTo())
        );
    }

    public function test_a_named_recipient_with_an_invalid_address_fails_the_notification_without_sending(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        self::assertSame(OrphanedResourceNotificationResult::Failed, $this->notifier($mailer, 'My Website <not an email>')->notify($this->changed()));
        self::assertTrue($this->logs->hasErrorThatContains('My Website <not an email>'));
    }

    public function test_nothing_is_sent_when_the_report_is_unchanged(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');
        $change = new OrphanedResourceReportChange($this->report(components: ['/c/1']), $this->report(components: ['/c/1']));

        self::assertSame(OrphanedResourceNotificationResult::Unchanged, $this->notifier($mailer)->notify($change));
    }

    public function test_nothing_is_sent_for_a_first_empty_report(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        self::assertSame(OrphanedResourceNotificationResult::Unchanged, $this->notifier($mailer)->notify(new OrphanedResourceReportChange($this->report(), null)));
    }

    public function test_a_change_is_emailed_to_every_recipient_with_the_counts_the_new_iris_and_the_admin_page_link(): void
    {
        $mailer = $this->recordingMailer();

        $result = $this->notifier($mailer)->notify(new OrphanedResourceReportChange(
            $this->report(['/g/1'], [], ['/c/1', '/c/2']),
            $this->report(['/g/1'], ['/p/1'], ['/c/1']),
        ));

        self::assertSame(OrphanedResourceNotificationResult::Sent, $result);
        self::assertCount(1, $mailer->messages);
        $email = $mailer->messages[0];
        self::assertInstanceOf(TemplatedEmail::class, $email);
        self::assertSame(self::RECIPIENTS, array_map(static fn ($address) => $address->getAddress(), $email->getTo()));
        self::assertSame('Orphaned resources changed on Test Website', $email->getSubject());
        self::assertSame('@SilverbackApiComponents/emails/orphaned_resources_changed.html.twig', $email->getHtmlTemplate());
        $context = $email->getContext();
        self::assertSame('Test Website', $context['website_name']);
        self::assertSame(['componentGroups' => 1, 'componentPositions' => 0, 'components' => 2], $context['counts']);
        self::assertSame(['componentGroups' => [], 'componentPositions' => [], 'components' => ['/c/2']], $context['added']);
        self::assertSame(1, $context['resolved_count']);
        self::assertSame('https://admin.example.com/_cwa/orphaned', $context['admin_url']);
        self::assertStringStartsWith(OrphanedResourcesChangedEmailFactory::MESSAGE_ID_PREFIX . '-', $email->getHeaders()->get('X-Message-ID')?->getBodyAsString() ?? '');
    }

    public function test_the_admin_page_path_and_subject_are_configurable(): void
    {
        $mailer = $this->recordingMailer();

        $this->notifier($mailer, adminPagePath: 'admin/orphans', subject: 'Orphans at {{ website_name }}')->notify($this->changed());

        self::assertSame('https://admin.example.com/admin/orphans', $mailer->messages[0]->getContext()['admin_url']);
        self::assertSame('Orphans at Test Website', $mailer->messages[0]->getSubject());
    }

    public function test_a_request_origin_is_never_used_for_the_link(): void
    {
        $mailer = $this->recordingMailer();
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://api.example.com/', server: ['HTTP_ORIGIN' => 'https://evil.example.com']));

        $this->notifier($mailer, resolver: new RefererUrlResolver($requestStack, ['https://evil\.example\.com'], 'https://admin.example.com'))->notify($this->changed());

        self::assertSame('https://admin.example.com/_cwa/orphaned', $mailer->messages[0]->getContext()['admin_url']);
    }

    public function test_without_a_default_origin_the_email_is_sent_without_a_link_and_a_warning_is_logged(): void
    {
        $mailer = $this->recordingMailer();

        $result = $this->notifier($mailer, resolver: new RefererUrlResolver(new RequestStack()))->notify($this->changed());

        self::assertSame(OrphanedResourceNotificationResult::Sent, $result);
        self::assertNull($mailer->messages[0]->getContext()['admin_url']);
        self::assertTrue($this->logs->hasWarningThatContains('without a link to the admin page'));
    }

    public function test_an_unusable_default_origin_sends_the_email_without_a_link(): void
    {
        $mailer = $this->recordingMailer();

        $result = $this->notifier($mailer, resolver: new RefererUrlResolver(new RequestStack(), [], 'not an origin'))->notify($this->changed());

        self::assertSame(OrphanedResourceNotificationResult::Sent, $result);
        self::assertNull($mailer->messages[0]->getContext()['admin_url']);
        self::assertTrue($this->logs->hasWarningThatContains('without a link to the admin page'));
    }

    public function test_a_failed_send_is_logged_and_reported_as_failed(): void
    {
        $mailer = $this->createStub(MailerInterface::class);
        $transportException = new TransportException('Connection refused');
        $transportException->appendDebug('debug output');
        $mailer->method('send')->willThrowException($transportException);

        self::assertSame(OrphanedResourceNotificationResult::Failed, $this->notifier($mailer)->notify($this->changed()));

        $records = $this->logs->getRecords();
        self::assertCount(1, $records);
        self::assertSame(Level::Error, $records[0]->level);
        self::assertStringContainsString('Connection refused', $records[0]->message);
        self::assertInstanceOf(MailerTransportException::class, $records[0]->context['exception']);
        self::assertSame('debug output', $records[0]->context['exception']->getDebug());
    }

    public function test_a_failed_send_without_a_logger_is_still_reported_as_failed(): void
    {
        $mailer = $this->createStub(MailerInterface::class);
        $mailer->method('send')->willThrowException(new TransportException('Connection refused'));

        $notifier = new OrphanedResourceNotifier($mailer, $this->factory(), $this->resolver(), self::RECIPIENTS);

        self::assertSame(OrphanedResourceNotificationResult::Failed, $notifier->notify($this->changed()));
    }

    public function test_an_invalid_recipient_fails_the_notification_without_sending(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        self::assertSame(OrphanedResourceNotificationResult::Failed, $this->notifier($mailer, ['admin@example.com', 'not an email'])->notify($this->changed()));
        self::assertTrue($this->logs->hasErrorThatContains('not an email'));
    }

    /**
     * @return MailerInterface&object{messages: list<TemplatedEmail>}
     */
    private function recordingMailer(): MailerInterface
    {
        return new class implements MailerInterface {
            /** @var list<TemplatedEmail> */
            public array $messages = [];

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                \assert($message instanceof TemplatedEmail);
                $this->messages[] = $message;
            }
        };
    }

    private function changed(): OrphanedResourceReportChange
    {
        return new OrphanedResourceReportChange($this->report(components: ['/c/1']), null);
    }

    /**
     * @param list<string>|string $recipients
     */
    private function notifier(MailerInterface $mailer, array|string $recipients = self::RECIPIENTS, ?RefererUrlResolver $resolver = null, string $adminPagePath = '/_cwa/orphaned', string $subject = 'Orphaned resources changed on {{ website_name }}'): OrphanedResourceNotifier
    {
        return new OrphanedResourceNotifier($mailer, $this->factory($subject), $resolver ?? $this->resolver(), $recipients, $adminPagePath, new Logger('test', [$this->logs]));
    }

    private function factory(string $subject = 'Orphaned resources changed on {{ website_name }}'): OrphanedResourcesChangedEmailFactory
    {
        return new OrphanedResourcesChangedEmailFactory(new Environment(new ArrayLoader()), $subject, ['website_name' => 'Test Website']);
    }

    private function resolver(): RefererUrlResolver
    {
        return new RefererUrlResolver(new RequestStack(), [], 'https://admin.example.com');
    }

    /**
     * @param list<string> $componentGroups
     * @param list<string> $componentPositions
     * @param list<string> $components
     */
    private function report(array $componentGroups = [], array $componentPositions = [], array $components = []): OrphanedResourceReport
    {
        return new OrphanedResourceReport(new \DateTimeImmutable(), $componentGroups, $componentPositions, $components);
    }
}
