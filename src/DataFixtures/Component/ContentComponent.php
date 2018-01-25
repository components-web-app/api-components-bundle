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

    public function getComponent(): Component
    {
        return new Content();
    }

    public static function defaultOps(): array
    {
        return array_merge(parent::defaultOps(), [
            'lipsum' => ['5', 'medium', 'headers', 'code', 'decorate', 'link', 'bq', 'ul', 'ol'],
            'content' => null
        ]);
    }

    public function create($owner, array $ops = null): Component
    {
        /**
         * @var Content $component
         */
        $component = parent::create($owner, $ops);
        $ops = self::processOps($ops);
        if (!$ops['content']) {
            $res = $this->client->request('GET', 'http://loripsum.net/api/' . join('/', $ops['lipsum']));
            $component->setContent($res->getBody());
        } else {
            $component->setContent($ops['content']);
        }
        return $component;
    }
}
