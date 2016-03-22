<?php

namespace Stevebauman\Translation\Clients;

use Stevebauman\Translation\Contracts\Client;
use Stichoza\GoogleTranslate\TranslateClient;

class GoogleTranslate implements Client
{
    /** @var TranslateClient */
    protected $client;

    /**
     * @param TranslateClient $client
     */
    public function __construct(TranslateClient $client)
    {
        $this->client = $client;
    }

    /**
     * @inheritDoc
     */
    public function setSource($source = null)
    {
        return $this->client->setSource($source);
    }

    /**
     * @inheritDoc
     */
    public function setTarget($target)
    {
        return $this->client->setTarget($target);
    }

    /**
     * @inheritDoc
     */
    public function translate($text)
    {
        return $this->client->translate($text);
    }
}
