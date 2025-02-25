<?php

namespace App;

require_once __DIR__ . '/../vendor/autoload.php'; //ensure this is still included, as it will not be included by index.php when using namespaces.


use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;

/* The `CacheService` class is a PHP class that provides caching functionality using Symfony's Cache
component. It includes methods for storing data in the cache, retrieving data from the cache, and
clearing data from the cache. The class uses a `FilesystemAdapter` to manage the caching operations
and allows setting a time-to-live (TTL) for cached items. */

class CacheService {
	private FilesystemAdapter $cache;
	private int $cacheAge;

	public function __construct( string $cacheDir = null, int $cacheAge = 2592000 ) {
		if ( $cacheDir === null ) {
			$cacheDir = __DIR__ . '/../cache';
		}
		$this->cacheAge = $cacheAge;
		$this->cache    = new FilesystemAdapter( 'g-drive-cache', $cacheAge, $cacheDir );
	}

	/**
	 * The store function stores data in a cache with an optional time-to-live value.
	 *
	 * @param string $key The `key` parameter in the `store` function is used to uniquely identify the data
	 * being stored in the cache. It is typically a string that serves as a reference to the cached
	 * data.
	 * @param mixed $data The `data` parameter in the `store` function represents the information that you
	 * want to store in the cache. This could be any type of data such as strings, arrays, objects,
	 * etc. The function stores this data in the cache with the specified key and time-to-live (TTL)
	 *
	 * @return void In the `store` function, the `` is being returned after setting the expiration
	 * time for the cache item.
	 * @throws InvalidArgumentException
	 */
	public function store( string $key, mixed $data ): void {
		$this->cache->get( $key, function ( ItemInterface $item ) use ( $data ) {
			$item->expiresAfter( $this->cacheAge );

			return $data;
		} );
	}

	/**
	 * The get function retrieves an item from the cache by key, returning the item if it exists or
	 * null if it does not.
	 *
	 * @param string $key The `get` function takes a key as a parameter. This key is used to retrieve an item
	 * from the cache. If the item exists in the cache and is not expired, the function will return the
	 * item's value. If the item does not exist in the cache or is expired, the function
	 *
	 * @return mixed `get` method is returning the value associated with the given key from the cache. If
	 * the key is found in the cache and the item is considered a hit, then the value associated with
	 * that key is returned. If the key is not found in the cache or the item is not a hit, then `null`
	 * is returned.
	 * @throws InvalidArgumentException
	 */
	public function get( string $key ): mixed {
		return $this->cache->getItem( $key )->isHit() ? $this->cache->getItem( $key )->get() : null;
	}

	/**
	 * The clear function deletes an item from the cache using the specified key.
	 *
	 * @param string $key The `key` parameter in the `clear` function is used to specify the key of the item
	 * that needs to be cleared from the cache. This key is used to identify the specific item in the
	 * cache that should be deleted.
	 *
	 * @throws InvalidArgumentException
	 */
	public function clear( string $key ): void {
		$this->cache->deleteItem( $key );
	}
}
