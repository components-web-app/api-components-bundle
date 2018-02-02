<?php

use Behat\Behat\Context\Context;
use Behat\Symfony2Extension\Context\KernelAwareContext;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * Defines application features from the specific context.
 */
class KernelContext implements Context, KernelAwareContext
{
    /**
     * @var KernelInterface
     */
    private $kernel;
    /**
     * @var \Symfony\Component\PropertyAccess\PropertyAccessor
     */
    private $propertyAccessor;

    /**
     * Initializes context.
     *
     * Every scenario gets its own context instance.
     * You can also pass arbitrary arguments to the
     * context constructor through behat.yml.
     */
    public function __construct()
    {
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    /**
     * @param KernelInterface $kernel
     */
    public function setKernel(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * @param $path
     * @return string
     * @throws \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     */
    private function getPublicPath ($path)
    {
        return $this->kernel->getContainer()->getParameter('kernel.project_dir') . '/public/' . $path;
    }

    /**
     * @Then the public file path :path should exist
     * @throws \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     */
    public function filePathExists(string $path)
    {
        $fullPath = $this->getPublicPath($path);
        Assert::assertFileExists($fullPath, 'The file "' . $fullPath . '"" does not exist');
    }

    /**
     * @Then the public file path :path should not exist
     * @throws \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     */
    public function filePathDoesNotExists(string $path)
    {
        $fullPath = $this->getPublicPath($path);
        Assert::assertFileNotExists($fullPath, 'The file "' . $fullPath . '"" exists');
    }

    /**
     * @Then the service :service should have property :property with a value of :value
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @throws \Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException
     * @throws \Symfony\Component\PropertyAccess\Exception\InvalidArgumentException
     * @throws \Symfony\Component\PropertyAccess\Exception\AccessException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     */
    public function assertNodeValueIs(string $service, string $property, string $value)
    {
        Assert::assertEquals(
            $this->propertyAccessor->getValue($this->kernel->getContainer()->get($service), $property),
            $value
        );
    }
}
