<?php

namespace Silverback\ApiComponentBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Silverback\ApiComponentBundle\Entity\Component\Form\Form;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FormCacheClear extends Command
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var OutputInterface
     */
    private $output;

    public function __construct(
        EntityManagerInterface $em,
        ?string $name = null
    ) {
        $this->em = $em;
        parent::__construct($name);
    }

    /**
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    protected function configure(): void
    {
        $this
            ->setName('app:form:cache:clear')
            ->setDescription('Purges the varnish cache for forms where files have been updated')
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \ReflectionException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        $repo = $this->em->getRepository(Form::class);
        $forms = $repo->findAll();
        foreach ($forms as $form) {
            $this->updateFormTimestamp($form);
        }
        $this->em->flush();
    }

    /**
     * @param Form $form
     * @throws \ReflectionException
     */
    private function updateFormTimestamp(Form $form)
    {
        $formClass = $form->getFormType();
        $reflector = new \ReflectionClass($formClass);
        $dateTime = new \DateTime();
        $timestamp = filemtime($reflector->getFileName());

        $this->output->writeln(sprintf('<info>Checking timestamp for %s</info>', $formClass));
        if (!$form->getLastModified() || $timestamp !== $form->getLastModified()->getTimestamp()) {
            $dateTime->setTimestamp($timestamp);
            $form->setLastModified($dateTime);
            $this->output->writeln('<comment>Updated timestamp</comment>');
        }
    }
}
