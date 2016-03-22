<?php

namespace Stevebauman\Translation\Clients;

use GuzzleHttp\Client as GuzzleClient;
use Stevebauman\Translation\Contracts\Client;
use Illuminate\Contracts\Foundation\Application;

class GoogleTranslateApi implements Client
{
    /** @var GuzzleClient */
    protected $client;

    /** @var \Illuminate\Config\Repository Holds the current config instance. */
    protected $config;

    /** @var string API endpoint */
    protected $endpoint = 'https://www.googleapis.com/language/translate/v2';

    /** @var string source language */
    protected $source;

    /** @var string target language */
    protected $target;

    /**
     * @param Application $app
     * @param GuzzleClient $client
     */
    public function __construct(Application $app, GuzzleClient $client)
    {
        $this->config = $app->make('config');
        $this->client = $client;
    }

    /**
     * @param $text
     * @return string
     */
    public function translate($text)
    {
        $response = $this->client->request('GET', $this->endpoint, [
            'query' => [
                'key' => $this->getApiKey(),
                'format' => 'html', // text | html for source text
                'source' => $this->getSource(), // source language
                'target' => $this->getTarget(),
                'q' => $text,
            ]
        ]);

        return $this->parseResponse(json_decode($response->getBody()->getContents(), true));
    }

    /**
     * Extract and decode the translation response
     *
     * @param string $contents
     * @return mixed
     * @throws \Exception
     */
    protected function parseResponse($contents)
    {
        if (isset($contents['data'])) {
            // get translation result array
            $results = $contents['data']['translations'];
            $translatedText = $results[0]['translatedText'];

            if (!empty($translatedText)) {
                return $results[0]['translatedText'];
            }
        } elseif (isset($contents['error'])) {
            // return API error details
            throw new \Exception("Error: Result code {$contents['code']}: {$contents['message']}");
        } else {
            // It's bad. Real bad.
            throw new \Exception("Error: Unknown response from API endpoint");
        }
    }

    /**
     * Returns the api key from the configuration.
     *
     * @return string
     */
    protected function getApiKey()
    {
        return $this->config->get('translation.clients.api_key');
    }

    /**
     * Returns the source language.
     *
     * @return string
     */
    protected function getSource()
    {
        return $this->source;
    }

    /**
     * Returns the target language.
     *
     * @return string
     */
    protected function getTarget()
    {
        return $this->target;
    }

    /**
     * @inheritDoc
     */
    public function setSource($source = null)
    {
        return $this->source = $source;
    }

    /**
     * @inheritDoc
     */
    public function setTarget($target)
    {
        return $this->target = $target;
    }
}
