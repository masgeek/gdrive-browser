<?php

namespace App;

require_once __DIR__ . '/../vendor/autoload.php'; //ensure this is still included, as it will not be included by index.php when using namespaces.


use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;

class CacheService
{
    private FilesystemAdapter $cache;

    public function __construct()
    {
        $this->cache = new FilesystemAdapter('g-drive-cache', 2592000, __DIR__ . '/../cache');
    }

    /**
     * @param $key
     * @param $data
     * @param int $ttl
     * @return void
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function store($key, $data, int $ttl = 2592000): void
    {
        $this->cache->get($key, function (ItemInterface $item) use ($data, $ttl) {
            $item->expiresAfter($ttl);
            return $data;
        });
    }

    public function get($key)
    {
        return $this->cache->getItem($key)->isHit() ? $this->cache->getItem($key)->get() : null;
    }

    public function clear($key)
    {
        $this->cache->deleteItem($key);
    }
}
