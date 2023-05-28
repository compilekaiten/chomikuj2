<?php

declare(strict_types=1);

use Chomikuj\Entity\Folder;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class FolderTest extends TestCase {
    public function testCanBeCreatedWithValidData(): void {
        $this->assertInstanceOf(Folder::class, new Folder(123, 'foldername', '/path', []));
    }
}
