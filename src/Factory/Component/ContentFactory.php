<?php

namespace Silverback\ApiComponentBundle\Factory\Component;

use Doctrine\Common\Persistence\ObjectManager;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Component\Content\Content;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;

class ContentFactory extends AbstractComponentFactory
{
    /**
     * @var \GuzzleHttp\Client
     */
    private $client;

    public function __construct(ObjectManager $manager)
    {
        parent::__construct($manager);
        $this->client = new Client();
    }

    public function getComponent(): AbstractComponent
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

    /**
     * @param AbstractContent $owner
     * @param array|null $ops
     * @return AbstractComponent
     * @throws \InvalidArgumentException
     */
    public function create(AbstractContent $owner, array $ops = null): AbstractComponent
    {
        /**
         * @var Content $component
         */
        $component = parent::create($owner, $ops);
        $ops = $this->processOps($ops);
        if (!$ops['content']) {
            $url = 'http://loripsum.net/api/' . implode('/', $ops['lipsum']);
            try {
                $res = $this->client->request(
                    'GET',
                    $url,
                    [ 'connect_timeout' => 3, 'read_timeout' => 2, 'timeout' => 5 ]
                );
                $component->setContent($res->getBody());
            } catch (RequestException $e) {
                $component->setContent(
                    vsprintf(
                        '<p><b>Request Exception</b>: %s<br/><small><a href="%s">%s</a></small></p>',
                        [ $e->getMessage(), $url, $url ]
                    )
                );
            }
        } else {
            $component->setContent($ops['content']);
        }
        return $component;
    }
}
