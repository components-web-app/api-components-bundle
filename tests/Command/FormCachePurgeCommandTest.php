<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\Tests\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Silverback\ApiComponentsBundle\Entity\Component\Form;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Form\TestType;
use Silverback\ApiComponentsBundle\Tests\Repository\AbstractRepositoryTest;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class FormCachePurgeCommandTest extends AbstractRepositoryTest
{
    /**
     * @var EntityManagerInterface|ObjectManager|null
     */
    protected $entityManager;
    private Command $command;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $container = $kernel->getContainer();
        $this->managerRegistry = $container->get('doctrine');
        $this->clearSchema($this->managerRegistry);
        $application = new Application($kernel);
        $this->command = $application->find('silverback:api-components:form-cache-purge');
    }

    public function test_execute_with_no_forms(): void
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([]);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Skipping form component cache clear / timestamp updates - No forms components found', $output);
        $this->assertStringContainsString('Form cache purge complete', $output);
    }

    public function test_execute_with_form(): void
    {
        $form = new Form();
        $form->formType = TestType::class;
        $this->entityManager->persist($form);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([]);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString(sprintf('Checking timestamp for %s', TestType::class), $output);
        $this->assertStringContainsString('Updated timestamp', $output);
        $this->assertStringContainsString('Form cache purge complete', $output);
    }
}
