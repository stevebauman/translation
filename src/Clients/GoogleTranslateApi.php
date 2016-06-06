<?php

namespace Stevebauman\Translation\Clients;

use ErrorException;
use GuzzleHttp\ClientInterface;
use Illuminate\Contracts\Foundation\Application;
use Stevebauman\Translation\Contracts\Client;

class GoogleTranslateApi implements Client
{
    /** @var ClientInterface */
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
     * @param Application     $app
     * @param ClientInterface $client
     */
    public function __construct(Application $app, ClientInterface $client)
    {
        $this->config = $app->make('config');
        $this->client = $client;
    }

    /**
     * @param $text
     *
     * @return string
     */
    public function translate($text)
    {
        $response = $this->client->request('GET', $this->endpoint, [
            'query' => [
                'key'    => $this->getApiKey(),
                'format' => 'html', // text | html for source text
                'source' => $this->getSource(), // source language
                'target' => $this->getTarget(),
                'q'      => $text,
            ],
        ]);

        return $this->parseResponse(json_decode($response->getBody(), true));
    }

    /**
     * Extract and decode the translation response.
     *
     * @param array $contents
     *
     * @throws \Exception
     *
     * @return mixed
     */
    protected function parseResponse($contents)
    {
        if (isset($contents['data'])) {
            // get translation result array
            $results = $contents['data']['translations'];

            // return the first result in the array
            return $results[0]['translatedText'];
        } elseif (isset($contents['error'])) {
            // return API error details
            throw new ErrorException("Error: Result code {$contents['code']}: {$contents['message']}");
        } else {
            // It's bad. Real bad.
            throw new ErrorException('Error: Unknown response from API endpoint');
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
     * {@inheritdoc}
     */
    public function setSource($source = null)
    {
        return $this->source = $source;
    }

    /**
     * {@inheritdoc}
     */
    public function setTarget($target)
    {
        return $this->target = $target;
    }
}
