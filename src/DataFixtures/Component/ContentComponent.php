<?php

namespace Silverback\ApiComponentBundle\DataFixtures\Component;

use Doctrine\Common\Persistence\ObjectManager;
use GuzzleHttp\Client;
use Silverback\ApiComponentBundle\Entity\Component\Component;
use Silverback\ApiComponentBundle\Entity\Component\Content;

class ContentComponent extends AbstractComponent
{
    /**
     * @var \GuzzleHttp\Client
     */
    private $client;

    public function __construct(
        ObjectManager $manager
    )
    {
        parent::__construct($manager);
        $this->client = new Client();
    }

    public static function getComponent(): Component
    {
        return new Content();
    }

    public static function defaultOps(): array
    {
        return [
            'lipsum' => ['5', 'medium', 'headers', 'code', 'decorate', 'link', 'bq', 'ul', 'ol']
        ];
    }

    public function create($owner, array $ops = null): Component
    {
        /**
         * @var Content $component
         */
        $component = parent::create($owner, $ops);
        $ops = self::processOps($ops);
        $res = $this->client->request('GET', 'http://loripsum.net/api/' . join('/', $ops['lipsum']));
        $component->setContent($res->getBody());
        return $component;
    }
}
