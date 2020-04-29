<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Tests\config;

use League\Flysystem\Local\LocalFilesystemAdapter;
use Psr\Log\LoggerInterface;
use Silverback\ApiComponentBundle\Flysystem\FilesystemProvider;
use Silverback\ApiComponentBundle\Tests\Functional\TestBundle\Form\TestType;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container) {
    $services = $container->services();
    $services
        ->defaults()
        ->autoconfigure()
        ->autowire()
        ->private();

    $services
        ->alias('test.logger', LoggerInterface::class)
        ->public();

    $services
        ->load('Silverback\\ApiComponentBundle\\Tests\\Functional\\TestBundle\\DataFixtures\\', '../../TestBundle/DataFixtures')
        ->tag('doctrine.fixture.orm');

    $services
        ->set(TestType::class);

    $services
        ->set(LocalFilesystemAdapter::class)
        ->args([
            '%kernel.project_dir%/var/storage/default',
        ])
        ->tag(FilesystemProvider::FILESYSTEM_ADAPTER_TAG, ['alias' => 'local']);
};
