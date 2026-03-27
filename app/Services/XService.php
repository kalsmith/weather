<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Subscriber\Oauth\Oauth1;
use Illuminate\Support\Facades\Log;

class XService
{
    private $client;
    private $uploadClient;
    private $region;

    public function __construct(string $region)
    {
        $this->region = strtoupper($region);

        // Configuración de OAuth 1.0a dinámica por región
        $stack = HandlerStack::create();
        $middleware = new Oauth1([
            'consumer_key'    => env("TWITTER_API_KEY_{$this->region}"),
            'consumer_secret' => env("TWITTER_API_SECRET_{$this->region}"),
            'token'           => env("TWITTER_ACCESS_TOKEN_{$this->region}"),
            'token_secret'    => env("TWITTER_ACCESS_SECRET_{$this->region}"),
            'signature_method' => 'HMAC-SHA1',
        ]);
        $stack->push($middleware);

        // Cliente para Tweets (API v2)
        $this->client = new Client([
            'base_uri' => 'https://api.twitter.com/2/',
            'handler'  => $stack,
            'auth'     => 'oauth',
        ]);

        // Cliente para Subir Imágenes (API v1.1 - Aún requerida para media)
        $this->uploadClient = new Client([
            'base_uri' => 'https://upload.twitter.com/1.1/',
            'handler'  => $stack,
            'auth'     => 'oauth',
        ]);
    }

    /**
     * Envía un Tweet con o sin imagen.
     */
    public function sendTweet(string $text, ?string $imagePath = null)
    {
        try {
            $payload = ['text' => $text];

            // 1. Si hay imagen, primero la subimos a Twitter
            if ($imagePath && file_exists($imagePath)) {
                $mediaId = $this->uploadMedia($imagePath);
                if ($mediaId) {
                    $payload['media'] = ['media_ids' => [$mediaId]];
                }
            }

            // 2. Publicar el Tweet
            $response = $this->client->post('tweets', [
                'json' => $payload
            ]);

            return json_decode($response->getBody()->getContents(), true);

        } catch (\Exception $e) {
            Log::error("Error en XService ({$this->region}): " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Sube el archivo de imagen y retorna el media_id.
     */
    private function uploadMedia(string $path): ?string
    {
        try {
            $response = $this->uploadClient->post('media/upload.json', [
                'multipart' => [
                    [
                        'name'     => 'media',
                        'contents' => fopen($path, 'r'),
                    ],
                    [
                        'name'     => 'media_category',
                        'contents' => 'tweet_image',
                    ],
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            return $data['media_id_string'] ?? null;

        } catch (\Exception $e) {
            Log::error("Fallo subida de imagen a X ({$this->region}): " . $e->getMessage());
            return null;
        }
    }
}
