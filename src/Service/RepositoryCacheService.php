<?php

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Repository Caching Service
 * Provides caching functionality for repository operations
 */
class RepositoryCacheService
{
    private const DEFAULT_TTL = 300; // 5 minutes

    public function __construct(
        private CacheInterface $cache
    ) {}

    /**
     * Cache a repository result
     */
    public function remember(string $key, callable $callback, int $ttl = self::DEFAULT_TTL): mixed
    {
        return $this->cache->get($key, function (ItemInterface $item) use ($callback, $ttl) {
            $item->expiresAfter($ttl);
            return $callback();
        });
    }

    /**
     * Invalidate cache entries by pattern
     */
    public function invalidate(string $pattern): void
    {
        // Note: This requires cache implementation that supports tag-based invalidation
        // For simple implementations, we can use key prefixes
        $this->cache->delete($pattern);
    }

    /**
     * Clear all cache entries for a specific entity
     */
    public function clearEntityCache(string $entityClass): void
    {
        $entityName = strtolower(basename(str_replace('\\', '/', $entityClass)));
        $this->invalidate($entityName . '_*');
    }

    /**
     * Generate cache key for entity queries
     */
    public function generateKey(string $entityClass, string $method, array $params = []): string
    {
        $entityName = strtolower(basename(str_replace('\\', '/', $entityClass)));
        $paramString = $params ? '_' . md5(serialize($params)) : '';
        
        return "{$entityName}_{$method}{$paramString}";
    }
}