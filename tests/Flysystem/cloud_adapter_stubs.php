<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace League\Flysystem\GoogleCloudStorage {
    use Silverback\ApiComponentsBundle\Tests\Flysystem\RecordingFilesystemAdapter;

    if (!class_exists(GoogleCloudStorageAdapter::class)) {
        class GoogleCloudStorageAdapter extends RecordingFilesystemAdapter
        {
        }
    }
}

namespace League\Flysystem\AwsS3V3 {
    use Silverback\ApiComponentsBundle\Tests\Flysystem\RecordingFilesystemAdapter;

    if (!class_exists(AwsS3V3Adapter::class)) {
        class AwsS3V3Adapter extends RecordingFilesystemAdapter
        {
        }
    }
}

namespace League\Flysystem\AsyncAwsS3 {
    use Silverback\ApiComponentsBundle\Tests\Flysystem\RecordingFilesystemAdapter;

    if (!class_exists(AsyncAwsS3Adapter::class)) {
        class AsyncAwsS3Adapter extends RecordingFilesystemAdapter
        {
        }
    }
}
