<?php

namespace Tests\Unit;

use App\Support\DropboxSharedThumbnail;
use Tests\TestCase;

class DropboxSharedThumbnailTest extends TestCase
{
    public function test_picks_the_poster_named_for_the_hook(): void
    {
        $entries = $this->folderEntries();

        $this->assertSame(
            'https://www.dropbox.com/scl/fo/example/hash/Partnership%20Thumbnail.jpg?rlkey=example',
            DropboxSharedThumbnail::pick($entries, ['Partnership'])
        );
        $this->assertSame(
            'https://www.dropbox.com/scl/fo/example/hash/Student%20Performance%20Thumbnail.png?rlkey=example',
            DropboxSharedThumbnail::pick($entries, ['Student Performance Hook'])
        );
    }

    public function test_short_hook_does_not_match_a_longer_title(): void
    {
        $entries = [
            $this->image('Problem Thumbnail.jpg'),
            $this->image('Live Performance Problem Thumbnail.jpg'),
        ];

        $this->assertStringContainsString(
            'Problem%20Thumbnail.jpg',
            (string) DropboxSharedThumbnail::pick($entries, ['Problem'])
        );
        $this->assertStringContainsString(
            'Live%20Performance%20Problem',
            (string) DropboxSharedThumbnail::pick($entries, ['Live Performance Problem'])
        );
    }

    public function test_unmatched_hook_stays_empty_when_the_folder_has_several_posters(): void
    {
        $this->assertNull(DropboxSharedThumbnail::pick($this->folderEntries(), ['Megaphone']));
    }

    public function test_single_poster_is_used_when_the_hook_does_not_match(): void
    {
        $only = [$this->image('Trust Thumbnail.jpg')];

        $this->assertStringContainsString(
            'Trust%20Thumbnail.jpg',
            (string) DropboxSharedThumbnail::pick($only, ['Something Else'])
        );
    }

    /**
     * @return array<int, array{filename: string, href: string}>
     */
    private function folderEntries(): array
    {
        return [
            ['filename' => 'Partnership Hook .mp4', 'href' => 'https://www.dropbox.com/scl/fo/example/hash/Partnership%20Hook.mp4?rlkey=example'],
            $this->image('Partnership Thumbnail.jpg'),
            ['filename' => 'Student Performance Hook .mp4', 'href' => 'https://www.dropbox.com/scl/fo/example/hash/Student%20Performance%20Hook.mp4?rlkey=example'],
            $this->image('Student Performance Thumbnail.png'),
            $this->image('Trust Thumbnail.jpg'),
        ];
    }

    /**
     * @return array{filename: string, href: string}
     */
    private function image(string $name): array
    {
        return [
            'filename' => $name,
            'href' => 'https://www.dropbox.com/scl/fo/example/hash/'.rawurlencode($name).'?rlkey=example',
        ];
    }
}
