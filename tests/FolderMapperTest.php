<?php

declare(strict_types=1);

use Chomikuj\Exception\ChomikujException;
use Chomikuj\Mapper\FolderMapper;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class FolderMapperTest extends TestCase {
    public function testCanBeCreated(): void {
        $this->assertInstanceOf(FolderMapper::class, new FolderMapper());
    }

    public function testMapHtmlResponseThrowsExceptionOnInvalidData(): void {
        $folderMapper = new FolderMapper();

        $this->expectException(ChomikujException::class);
        $folderMapper->mapHtmlResponseToFolders(new Response());
    }
}
