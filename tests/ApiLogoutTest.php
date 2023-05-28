<?php

declare(strict_types=1);

use Chomikuj\Exception\ChomikujException;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/FakeApiFactory.php';

/**
 * @internal
 *
 * @coversNothing
 */
final class ApiLogoutTest extends TestCase {
    public function testLogoutResetsUsernamePropertyToNull(): void {
        $api = FakeApiFactory::getApi('username', [
            new Response(200, [], 'whatever'),
        ]);

        $api->logout();

        $reflection = new ReflectionClass($api);
        $property = $reflection->getProperty('username');
        $property->setAccessible(TRUE);

        $this->assertEquals(NULL, $property->getValue($api));
    }

    public function testLogoutThrowsExceptionOnInvalidResponse(): void {
        $api = FakeApiFactory::getApi('username', [
            new Response(400, [], 'definitely not 200'),
        ]);

        $this->expectException(ChomikujException::class);
        $api->logout();
    }
}
