<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Trait;

use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Stub\HubStub;
use Symfony\Bundle\MercureBundle\DataCollector\MercureDataCollector;
use Symfony\Component\Mercure\Update;

trait MercureTrait
{
    /**
     * @return list<Update>
     */
    protected static function mercureUpdates(): array
    {
        return HubStub::getPublished();
    }

    /**
     * @return list<Update>
     */
    protected static function mercureUpdatesInLastRequest(): array
    {
        $profile = self::getClient()->getProfile();
        self::assertNotFalse($profile, 'The request was not profiled.');
        $collector = $profile->getCollector('mercure');
        \assert($collector instanceof MercureDataCollector);

        return array_values(array_map(static fn (array $message): Update => $message['object'], $collector->getHubs()['default']['messages'] ?? []));
    }

    /**
     * @return list<Update>
     */
    protected static function mercureUpdatesFor(string $iri): array
    {
        return array_values(array_filter(
            static::mercureUpdates(),
            static fn (Update $update): bool => \in_array($iri, array_map(static fn (string $topic): string => parse_url($topic, \PHP_URL_PATH) ?: $topic, $update->getTopics()), true),
        ));
    }

    protected static function mercureUpdateFor(string $iri, bool $private): Update
    {
        $matching = array_values(array_filter(static::mercureUpdatesFor($iri), static fn (Update $update): bool => $update->isPrivate() === $private));
        self::assertCount(1, $matching, \sprintf('Expected one %s Mercure update for %s: %s', $private ? 'private' : 'public', $iri, static::describeMercureUpdates()));

        return $matching[0];
    }

    /**
     * @return array<mixed>
     */
    protected static function mercureUpdateData(Update $update): array
    {
        return json_decode($update->getData(), true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<string>
     */
    protected static function mercureTopicsWithoutOrigin(Update $update): array
    {
        return array_map(static function (string $topic): string {
            $query = parse_url($topic, \PHP_URL_QUERY);

            return (parse_url($topic, \PHP_URL_PATH) ?: $topic) . (null === $query ? '' : '?' . $query);
        }, $update->getTopics());
    }

    protected static function assertMercureUpdatePublishedFor(string $iri): void
    {
        self::assertNotEmpty(static::mercureUpdatesFor($iri), \sprintf('No Mercure update was published for %s: %s', $iri, static::describeMercureUpdates()));
    }

    protected static function assertNoMercureUpdatePublished(): void
    {
        self::assertSame([], static::mercureUpdates(), \sprintf('Mercure updates were published: %s', static::describeMercureUpdates()));
    }

    protected static function assertNoPublicMercureUpdateContains(string $text): void
    {
        foreach (static::mercureUpdates() as $update) {
            self::assertFalse(!$update->isPrivate() && str_contains($update->getData(), $text), \sprintf('A public Mercure update contains %s: %s', $text, $update->getData()));
        }
    }

    protected static function describeMercureUpdates(): string
    {
        return implode('; ', array_map(static fn (Update $update): string => \sprintf('%s %s %s', $update->isPrivate() ? 'private' : 'public', implode(', ', $update->getTopics()), $update->getData()), static::mercureUpdates()));
    }
}
