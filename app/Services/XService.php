<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Subscriber\Oauth\Oauth1;
use Illuminate\Support\Facades\Log;

class XService
{
    protected array $config;
    protected string $region;

    public function __construct(string $region)
    {
        $this->region = strtoupper($region);

        $this->config = [
            'consumer_key'    => env("TWITTER_API_KEY_{$this->region}"),
            'consumer_secret' => env("TWITTER_API_SECRET_{$this->region}"),
            'token'           => env("TWITTER_ACCESS_TOKEN_{$this->region}"),
            'token_secret'    => env("TWITTER_ACCESS_SECRET_{$this->region}"),
        ];

        foreach ($this->config as $key => $value) {
            if (empty($value)) {
                throw new \Exception("Falta la configuración {$key} para la región {$this->region} en el .env");
            }
        }
    }

    /**
     * Envía un tweet con o sin imagen.
     */
    public function sendTweet(string $text, ?string $imagePath = null)
    {
        try {
            $payload = ['text' => $text];

            // 1. Si hay imagen, la subimos primero y la adjuntamos al payload
            if ($imagePath && file_exists($imagePath)) {
                $mediaId = $this->uploadMedia($imagePath);
                $payload['media'] = [
                    'media_ids' => [(string)$mediaId]
                ];
            }

            // 2. Configurar cliente Oauth1 para el Tweet v2
            $stack = HandlerStack::create();
            $middleware = new Oauth1($this->config);
            $stack->push($middleware);

            $client = new Client([
                'base_uri' => 'https://api.twitter.com/2/',
                'handler'  => $stack,
                'auth'     => 'oauth',
            ]);

            $response = $client->post('tweets', [
                'json' => $payload
            ]);

            return json_decode($response->getBody()->getContents(), true);

        } catch (\Exception $e) {
            Log::error("Error publicando en X ({$this->region}): " . $e->getMessage());
            throw $e;
        }
    }

    private function uploadMedia(string $imagePath)
    {
        $stack = HandlerStack::create();
        $middleware = new Oauth1($this->config);
        $stack->push($middleware);

        $client = new Client([
            'base_uri' => 'https://upload.twitter.com/1.1/',
            'handler'  => $stack,
            'auth'     => 'oauth',
        ]);

        $response = $client->post('media/upload.json', [
            'multipart' => [
                [
                    'name'     => 'media',
                    'contents' => fopen($imagePath, 'r'),
                ],
            ],
        ]);

        $result = json_decode($response->getBody()->getContents(), true);

        if (!isset($result['media_id_string'])) {
            throw new \Exception("No se pudo obtener Media ID de Twitter.");
        }

        return $result['media_id_string'];
    }
}
