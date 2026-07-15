<?php

namespace App\Cache;

use App\Support\StoragePathGuard;
use Illuminate\Cache\FileStore;
use Throwable;

/**
 * File cache that recovers when storage/framework/cache/data (or shard dirs)
 * disappear mid-request after optimize:clear / cache:clear.
 */
class ResilientFileStore extends FileStore
{
    protected function ensureCacheDirectoryExists($path)
    {
        StoragePathGuard::ensureCacheShardForPath($path);
        parent::ensureCacheDirectoryExists($path);
    }

    public function put($key, $value, $seconds)
    {
        return $this->retryMissingDir(fn () => parent::put($key, $value, $seconds), $this->path($key));
    }

    public function add($key, $value, $seconds)
    {
        return $this->retryMissingDir(fn () => parent::add($key, $value, $seconds), $this->path($key));
    }

    public function forever($key, $value)
    {
        return $this->retryMissingDir(fn () => parent::forever($key, $value), $this->path($key));
    }

    public function lock($name, $seconds = 0, $owner = null)
    {
        return $this->retryMissingDir(
            fn () => parent::lock($name, $seconds, $owner),
            ($this->lockDirectory ?? $this->directory).'/locks/'.sha1($name)
        );
    }

    /**
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    protected function retryMissingDir(callable $callback, string $path)
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            if (! $this->isMissingDirectoryError($e)) {
                throw $e;
            }

            StoragePathGuard::ensureCacheShardForPath($path);

            return $callback();
        }
    }

    protected function isMissingDirectoryError(Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'Failed to open stream')
            || str_contains($message, 'No such file or directory')
            || str_contains($message, 'failed to open stream');
    }
}
