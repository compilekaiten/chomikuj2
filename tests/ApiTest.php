<?php

declare(strict_types=1);

use Chomikuj\Api;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class ApiTest extends TestCase {
    public function testCanBeCreated(): void {
        $client = new Client([
            'handler' => HandlerStack::create(new MockHandler()),
        ]);

        $this->assertInstanceOf(Api::class, new Api($client));
    }

    public function testCannotBeCreatedWithWrongTypeArgument(): void {
        $this->expectException(TypeError::class);
        new Api('certainly not GuzzleHttp\Client');
    }
}
