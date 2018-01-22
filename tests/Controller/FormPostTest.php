<?php

namespace Silverback\ApiComponentBundle\Tests\Controller;

use Monolog\Handler\TestHandler;
use Silverback\ApiComponentBundle\Tests\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\HttpKernel\Client;

class FormPostTest extends WebTestCase
{
    protected static $application;

    /**
     * @throws \Exception
     */
    public function setUp()
    {
        self::runCommand('doctrine:database:drop --force');
        self::runCommand('doctrine:database:create');
        self::runCommand('doctrine:schema:create');
        self::runCommand('doctrine:fixtures:load');
    }

    /**
     * @param $command
     * @return int
     * @throws \Exception
     */
    protected static function runCommand($command)
    {
        $command = sprintf('%s --quiet', $command);

        return self::getApplication()->run(new StringInput($command));
    }

    /**
     * @return Application
     */
    protected static function getApplication()
    {
        if (null === self::$application) {
            $client = self::getClient();
            self::$application = new Application($client->getKernel());
            self::$application->setAutoExit(false);
        }
        return self::$application;
    }

    public function testFormPost()
    {
        $client = $this->getClient();
        $client->request(
            'POST',
            '/forms/9/submit',
            array(),
            array(),
            array(
                'CONTENT_TYPE' => 'application/json;charset=UTF-8'
            ),
            '{"test":{"name":"Name"}}'
        );
        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent());

        $container = static::$kernel->getContainer();
        $testHandler = false;
        foreach ($container->get('test.logger')->getHandlers() as $handler) {
            if ($handler instanceof TestHandler) {
                $testHandler = $handler;
                break;
            }
        }

        if (!$testHandler) {
            throw new \RuntimeException('Oops, not exist "test" handler in monolog.');
        }
        $this->assertTrue($testHandler->hasInfo('Form submitted'), 'Handler should have logged info');
    }

    /**
     * @param string|null $env
     * @return Client
     */
    private static function getClient(string $env = null)
    {
        return static::createClient([
            'environment' => $env ?: self::getEnv(),
            'debug'       => false,
        ]);
    }

    protected static function getKernelClass()
    {
        return Kernel::class;
    }

    public static function getEnv()
    {
        return 'test';
    }
}
