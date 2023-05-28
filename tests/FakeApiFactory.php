<?php

use Chomikuj\Api;
use Chomikuj\Service\FolderTicksService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

class FakeApiFactory {
    public static function getApi(?string $loggedAs = NULL, ?array $responses = NULL, ?array &$container = NULL): Api {
        if (NULL === $container) {
            // Without middleware
            $client = new Client([
                'handler' => HandlerStack::create(new MockHandler($responses)),
                'http_errors' => FALSE,
            ]);
        } else {
            // With middleware
            $history = Middleware::history($container);
            $stack = HandlerStack::create(new MockHandler($responses));
            $stack->push($history);
            $client = new Client([
                'handler' => $stack,
                'http_errors' => FALSE,
            ]);
        }

        // Set up Api
        $api = new Api($client, NULL, NULL, new FolderTicksService($client));

        // Artificially set username of logged-in user
        if (NULL !== $loggedAs) {
            $reflection = new ReflectionClass($api);
            $property = $reflection->getProperty('username');
            $property->setAccessible(TRUE);
            $property->setValue($api, $loggedAs);
        }

        return $api;
    }

    public static function getTokenResponse() {
        return new Response(200, [], '<input name="__RequestVerificationToken" type="hidden" value="SOME_TOKEN_VALUE" />');
    }
}
