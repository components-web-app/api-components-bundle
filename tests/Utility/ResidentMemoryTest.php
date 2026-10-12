<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Utility;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Utility\ResidentMemory;

class ResidentMemoryTest extends TestCase
{
    private string $statusFile;

    protected function setUp(): void
    {
        $this->statusFile = (string) tempnam(sys_get_temp_dir(), 'status');
    }

    protected function tearDown(): void
    {
        @unlink($this->statusFile);
    }

    public function test_it_reads_the_resident_set_size_from_the_proc_status_file(): void
    {
        file_put_contents($this->statusFile, "Name:\tphp\nVmPeak:\t  900000 kB\nVmRSS:\t  524288 kB\nRssAnon:\t  400000 kB\n");

        self::assertSame(536870912, (new ResidentMemory($this->statusFile))->bytes());
    }

    public function test_it_reads_the_file_again_on_every_call(): void
    {
        $memory = new ResidentMemory($this->statusFile);
        file_put_contents($this->statusFile, "VmRSS:\t1 kB\n");
        self::assertSame(1024, $memory->bytes());

        file_put_contents($this->statusFile, "VmRSS:\t2 kB\n");
        self::assertSame(2048, $memory->bytes());
    }

    public function test_without_a_readable_status_file_it_falls_back_to_php_s_own_usage(): void
    {
        self::assertSame(memory_get_usage(true), (new ResidentMemory($this->statusFile . '-missing'))->bytes());
    }

    public function test_a_status_file_without_a_resident_set_size_falls_back_to_php_s_own_usage(): void
    {
        file_put_contents($this->statusFile, "Name:\tphp\nVmPeak:\t900000 kB\n");

        self::assertSame(memory_get_usage(true), (new ResidentMemory($this->statusFile))->bytes());
    }
}
