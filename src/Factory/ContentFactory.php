<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Factory;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Silverback\ApiComponentBundle\Entity\Component\Content\Content;

class ContentFactory
{
    private $client;
    private $lipsumOps;
    private $guzzleOps;

    public function __construct(Client $client, ?array $lipsumOps = null, array $guzzleOps = [])
    {
        $this->client = $client;
        $this->lipsumOps = $lipsumOps ?: [
            '5',
            'medium',
            'headers',
            'code',
            'decorate',
            'link',
            'bq',
            'ul',
            'ol'
        ];
        $this->guzzleOps = array_merge([
            'connect_timeout' => 3,
            'read_timeout' => 2,
            'timeout' => 5
        ], $guzzleOps);
    }

    public function create(?Content $contentEntity = null, ?array $lipsumOps = null, array $guzzleOps = []): Content
    {
        $content = $contentEntity ?: new Content();
        $content->setContent($this->getLipsumContent($lipsumOps, $guzzleOps));
        return $content;
    }

    public function getLipsumContent(?array $lipsumOps = null, array $guzzleOps = []): string
    {
        $lipsumOps = $lipsumOps ?: $this->lipsumOps;
        $guzzleOps = array_merge($this->guzzleOps, $guzzleOps);

        $url = 'https://loripsum.net/api/' . implode('/', $lipsumOps);
        try {
            $res = $this->client->request(
                'GET',
                $url,
                $guzzleOps
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
}
