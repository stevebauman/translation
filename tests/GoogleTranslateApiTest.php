<?php

namespace Stevebauman\Translation\Tests;

use GuzzleHttp\ClientInterface as Guzzle;
use Mockery as m;
use Psr\Http\Message\ResponseInterface;
use Stevebauman\Translation\Clients\GoogleTranslateApi;
use Stevebauman\Translation\Contracts\Client;

class GoogleTranslateApiTest extends FunctionalTestCase
{
    /** @var Guzzle */
    protected $guzzle;

    /** @var Client */
    protected $client;

    public function setUp()
    {
        parent::setUp();

        $this->guzzle = m::mock(Guzzle::class);
        $this->client = new GoogleTranslateApi($this->app, $this->guzzle);
    }

    public function testTranslateReturnsExpectedText()
    {
        $text = 'Hello, World!';

        $response = m::mock(ResponseInterface::class);

        $this->guzzle->shouldReceive('request')->once()->with('GET', 'https://www.googleapis.com/language/translate/v2', [
            'query' => [
                'key'    => config('translation.clients.api_key'),
                'format' => 'html',
                'source' => 'en',
                'target' => 'es',
                'q'      => $text,
            ],
        ])->andReturn($response);

        $response->shouldReceive('getBody')->andReturn(json_encode([
            'data' => [
                'translations' => [
                    [
                        'translatedText' => '¡Hola Mundo!',
                    ],
                ],
            ],
        ]));

        $this->client->setSource('en');
        $this->client->setTarget('es');

        $translation = $this->client->translate($text);
        $this->assertEquals('¡Hola Mundo!', $translation);
    }

    public function testFirstTranslatedResultIsReturned()
    {
        $text = 'Hello, World!';

        $response = m::mock(ResponseInterface::class);

        $this->guzzle->shouldReceive('request')->once()->with('GET', 'https://www.googleapis.com/language/translate/v2', [
            'query' => [
                'key'    => config('translation.clients.api_key'),
                'format' => 'html',
                'source' => 'en',
                'target' => 'es',
                'q'      => $text,
            ],
        ])->andReturn($response);

        $response->shouldReceive('getBody')->andReturn(json_encode([
            'data' => [
                'translations' => [
                    [
                        'translatedText' => '¡Hola Mundo!',
                    ],
                    [
                        'translatedText' => 'Cerveza',
                    ],
                ],
            ],
        ]));

        $this->client->setSource('en');
        $this->client->setTarget('es');

        $translation = $this->client->translate($text);
        $this->assertEquals('¡Hola Mundo!', $translation);
    }

    public function testNoDataInResponseThrowsException()
    {
        $text = 'Hello, World!';

        $response = m::mock(ResponseInterface::class);

        $this->guzzle->shouldReceive('request')->once()->with('GET', 'https://www.googleapis.com/language/translate/v2', [
            'query' => [
                'key'    => config('translation.clients.api_key'),
                'format' => 'html',
                'source' => 'en',
                'target' => 'es',
                'q'      => $text,
            ],
        ])->andReturn($response);

        $response->shouldReceive('getBody')->andReturn(json_encode([]));

        $this->client->setSource('en');
        $this->client->setTarget('es');

        $this->setExpectedException(\ErrorException::class);
        $this->client->translate($text);
    }

    public function testErrorInResponseThrowsException()
    {
        $text = 'Hello, World!';

        $response = m::mock(ResponseInterface::class);

        $this->guzzle->shouldReceive('request')->once()->with('GET', 'https://www.googleapis.com/language/translate/v2', [
            'query' => [
                'key'    => config('translation.clients.api_key'),
                'format' => 'html',
                'source' => 'en',
                'target' => 'es',
                'q'      => $text,
            ],
        ])->andReturn($response);

        $response->shouldReceive('getBody')->andReturn(json_encode(['error' => 'foo']));

        $this->client->setSource('en');
        $this->client->setTarget('es');

        $this->setExpectedException(\ErrorException::class);
        $this->client->translate($text);
    }
}
