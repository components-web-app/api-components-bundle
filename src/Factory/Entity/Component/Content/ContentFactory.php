<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Component\Content;

use Doctrine\Common\Persistence\ObjectManager;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Silverback\ApiComponentBundle\Entity\Component\Content\Content;
use Silverback\ApiComponentBundle\Factory\Entity\Component\AbstractComponentFactory;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class ContentFactory extends AbstractComponentFactory
{
    /**
     * @var \GuzzleHttp\Client
     */
    private $client;

    public function __construct(
        ObjectManager $manager,
        ValidatorInterface $validator,
        Client $client
    ) {
        parent::__construct($manager, $validator);
        $this->client = $client;
    }

    /**
     * @return array
     */
    private static function getGuzzleOps(): array
    {
        return [
            'connect_timeout' => 3,
            'read_timeout' => 2,
            'timeout' => 5
        ];
    }

    /**
     * @return string
     */
    private function getLipsumContent(): string
    {
        $url = 'http://loripsum.net/api/' . implode('/', $this->ops['lipsum']);
        try {
            $res = $this->client->request(
                'GET',
                $url,
                self::getGuzzleOps()
            );
            return (string) $res->getBody();
        } catch (RequestException $e) {
            return vsprintf(
                '<p><b>Request Exception</b>: %s<br/><small><a href="%s">%s</a></small></p>',
                [
                    $e->getMessage(),
                    $url,
                    $url
                ]
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): Content
    {
        $component = new Content();
        $this->init($component, $ops);

        if (\is_string($this->ops['content'])) {
            $component->setContent($this->ops['content']);
        } else {
            $component->setContent($this->getLipsumContent());
        }
        $this->validate($component);

        return $component;
    }

    /**
     * @inheritdoc
     */
    public static function defaultOps(): array
    {
        return array_merge(
            parent::defaultOps(),
            [
                'lipsum' => ['5', 'medium', 'headers', 'code', 'decorate', 'link', 'bq', 'ul', 'ol'],
                'content' => null
            ]
        );
    }
}
