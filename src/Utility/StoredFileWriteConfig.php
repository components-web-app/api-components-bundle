<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Utility;

final class StoredFileWriteConfig
{
    public const string GOOGLE_CLOUD_STORAGE_ADAPTER = 'League\Flysystem\GoogleCloudStorage\GoogleCloudStorageAdapter';

    public const array S3_ADAPTERS = [
        'League\Flysystem\AwsS3V3\AwsS3V3Adapter',
        'League\Flysystem\AsyncAwsS3\AsyncAwsS3Adapter',
    ];

    public static function withCacheControl(?object $adapter, array $config, ?string $cacheControl): array
    {
        if (null === $cacheControl) {
            return $config;
        }

        if (is_a($adapter, self::GOOGLE_CLOUD_STORAGE_ADAPTER)) {
            $config['metadata'] = [...($config['metadata'] ?? []), 'cacheControl' => $cacheControl];
        }

        foreach (self::S3_ADAPTERS as $s3Adapter) {
            if (is_a($adapter, $s3Adapter)) {
                $config['CacheControl'] = $cacheControl;
            }
        }

        return $config;
    }
}
