<?php
namespace GDO\DB;

use GDO\Core\GDO;
use GDO\Core\GDT_Hook;
use GDO\Util\FileUtil;
use GDO\Core\Module_Core;
use GDO\Core\Application;

/**
 * Cache is a global object cache, where each fetched object (with the same key) from the database results in the same instance.
 * This way you can never have two dangling out of sync users in your application.
 * It also saves a bit mem.
 * Of course this comes with a slight overhead.
 * As GDOv7 was written from scratch with this in mind, the overhead is quite small.
 * 
 * Suprising is the additional use of memcached (did not plan this) which adds a second layer of caching.
 * 
 * There are a few global memcached keys scattered across the application, fetching all rows or similiar stuff.
 * Those GDOs usually dont use memcached on a per row basis and gdoMemcached is false.
 * 
 * gdo_modules
 * gdo_country
 * gdo_language
 * 
 * The other memcached keys work on a per row basis with table_name_id as key.
 * 
 * @author gizmore
 * @version 7.0.0
 * @since 5.0.0
 */
class Cache
{
	#################
	### Memcached ###
	#################
	private static \Memcached $MEMCACHED; # Memcached server
	
	/**
	 * This holds the GDO that need a recache after the method has been executed.
	 * @var GDO[]
	 */
	private static array $RECACHING = [];

	public array $pkNames;   # Primary Key Column Names
    public array $pkColumns; # Primary Key Columns
    public string $tableName; # Cached transformed table name

	#################
	### Memcached ###
	#################
    /**
     * @var $all GDO[] All rows. @see GDO->allCached()
     */
    public array $all;       # 
    public int $allExpire; # Expiration time for allCached()
    
    /**
	 * @TODO no result should return null?
	 * @param string $key
	 * @return boolean
	 */
    public static function get($key)
    {
    	return GDO_MEMCACHE ? 
    		self::$MEMCACHED->get(MEMCACHEPREFIX.$key) :
    		false;
    }

    /**
     * Set a memcached item.
     * @param string $key
     * @param mixed $value
     * @param integer $expire
     */
    public static function set($key, $value, $expire=null) { if (GDO_MEMCACHE) self::$MEMCACHED->set(MEMCACHEPREFIX.$key, $value, $expire); }
    public static function replace($key, $value, $expire=null) { if (GDO_MEMCACHE) self::$MEMCACHED->replace(MEMCACHEPREFIX.$key, $value, $expire); }
    public static function remove($key) { if (GDO_MEMCACHE) self::$MEMCACHED->delete(MEMCACHEPREFIX.$key); }
	public static function flush() { if (GDO_MEMCACHE) self::$MEMCACHED->flush(); }
	public static function init()
	{
		if (GDO_MEMCACHE)
		{
			self::$MEMCACHED = new \Memcached();
			self::$MEMCACHED->addServer(GDO_MEMCACHE_HOST, GDO_MEMCACHE_PORT);
		}
		if (GDO_FILECACHE)
		{
		    FileUtil::createDir(self::filePath());
		}
	}
	
	#########################
	### GDO Process Cache ###
	#########################
	/**
	 * The table object is fine to keep clean?
	 */
	private GDO $table;
	
	/**
	 * @var GDO
	 */
	private GDO $dummy;
	
	/**
	 * Full classname
	 * @var string
	 */
	private string $klass;
	
	/**
	 * The single identity GDO cache
	 * @var GDO[]
	 */
	public array $cache = [];

	public function __construct(GDO $gdo)
	{
		$this->table = $gdo;
		$this->klass = get_class($gdo);
		$this->tableName = strtolower($gdo->gdoShortName());
	}
	
	public static function recacheHooks()
	{
		if (GDO_IPC && Application::$INSTANCE->isWebServer())
		{
            foreach (self::$RECACHING as $gdo)
            {
                GDT_Hook::callWithIPC('CacheInvalidate', $gdo->table()->cache->klass, $gdo->getID());
            }
		}
	}

	public function getDummy()
	{
	    return isset($this->dummy) ? $this->dummy : $this->newDummy();
	}
	
	private function newDummy()
	{
		$this->dummy = new $this->klass();
		return $this->dummy;
	}
	
	/**
	 * Try GDO Cache and Memcached.
	 * @param string $id
	 * @return GDO
	 */
	public function findCached(...$ids)
	{
		$id = implode(':', $ids);
		if (!isset($this->cache[$id]))
		{
			if ($mcached = self::get($this->tableName . $id))
			{
				$this->cache[$id] = $mcached;
			}
			else
			{
			    return false;
			}
		}
		return $this->cache[$id];
	}
	
	public function hasID($id) : bool
	{
		return isset($this->cache[$id]);
	}
	
	/**
	 * Only GDO Cache / No memcached initializer.
	 * @param array $assoc
	 * @return GDO
	 */
	public function initCached(array $assoc, bool $useCache=true) : GDO
	{
		$this->getDummy()->setGDOVars($assoc);
		$key = $this->dummy->getID();
		if (!isset($this->cache[$key]))
		{
			$this->cache[$key] = (new $this->klass())->setGDOVars($assoc)->setPersisted();
		}
		elseif ($useCache)
		{
			$this->cache[$key]->setGDOVars($assoc);
		}
		return $this->cache[$key];
	}
	
	public function clearCache() : void
	{
	    unset($this->all);
	    $this->cache = [];
	    $this->flush();
	}
	
	public function recache(GDO $object) : GDO
	{
		if (!$object->isPersisted())
		{
			return $object;
		}
			
		$back = $object;
		
		# GDO cache
		if ($back->gdoCached())
		{
    		$id = $object->getID();

    		# GDO single cache
			if (isset($this->cache[$id]))
			{
				$old = $this->cache[$id];
				$old->setGDOVars($object->getGDOVars());
				$back = $old;
			}
			else
			{
				$this->cache[$id] = $back;
			}
		}
		
		# Memcached
		if (GDO_MEMCACHE && $back->memCached())
		{
		    self::replace($back->gkey(), $back, GDO_MEMCACHE_TTL);
		}

	    # Mark for recache
		if ($back->gdoCached())
		{
		    if (isset($back->recache))
    	    {
    	        self::$RECACHING[] = $back->recaching();
    	    }
		}
		
		$back->tempReset();
		
		return $back;
	}
	
	public function uncache(GDO $object)
	{
	    # Mark for recache
	    if ( (!isset($object->recache)) && ($object->gdoCached()) )
	    {
	        self::$RECACHING[] = $object->recaching();
	    }
	    
	    $id = $object->getID();
	    unset($this->cache[$id]);

		if (GDO_MEMCACHE && $object->memCached())
		{
    		self::remove($object->gkey());
		}
	}
	
	/**
	 * memcached + gdo cache initializer
	 * @param array $assoc
	 * @return GDO
	 */
	public function initGDOMemcached(array $assoc, $useCache=true)
	{
		$this->getDummy()->setGDOVars($assoc);
		$key = $this->dummy->getID();
		if (!isset($this->cache[$key]))
		{
			$gkey = $this->dummy->gkey();
			if (false === ($mcached = self::get($gkey)))
			{
				$mcached = $this->dummy->setGDOVars($assoc)->setPersisted();
				if (GDO_MEMCACHE)
				{
					self::set($gkey, $mcached, GDO_MEMCACHE_TTL);
				}
    			$this->newDummy();
			}
			$this->cache[$key] = $mcached;
		}
		elseif ($useCache)
		{
			$this->cache[$key]->setGDOVars($assoc)->setPersisted();
		}
		return $this->cache[$key];
	}
	
	/**
	 * Check if the parameter is the GDO table object.
	 */
	public function isTable(GDO $gdo) : bool
	{
		return $gdo === $this->table;
	}
	
	##################
	### File cache ###
	##################
	/**
	 * Store an item in a file cash.
	 * You can use self::fileSet() instead, if you only want to cache a single string.
	 * @param string $key
	 * @param mixed $value
	 * @return boolean
	 */
	public static function fileSetSerialized($key, $value)
	{
		if (GDO_FILECACHE)
		{
			$content = serialize($value);
			return self::fileSet($key, $content);
		}
		return false;
	}
	
	/**
	 * Put cached content on the file system.
	 * @param string $key
	 * @param string $content
	 * @return boolean
	 */
	public static function fileSet($key, $content)
	{
	    if (GDO_FILECACHE)
	    {
		    $path = self::filePath($key);
		    return file_put_contents($path, $content);
	    }
        return false;
	}
	
	/**
	 * Check if we have a recent cache for a key.
	 */
	public static function fileHas(string $key, int $expire=GDO_MEMCACHE_TTL) : bool
	{
	    if (!GDO_FILECACHE)
	    {
	        return false;
	    }
	    $path = self::filePath($key);
	    if (!file_exists($path))
	    {
	        return false;
	    }
	    $time = filemtime($path);
	    if ( (Application::$TIME - $time) > $expire)
	    {
	        unlink($path);
	        return false;
	    }
	    return true;
	}

	/**
	 * Get a value from file cache and de-serialize.
	 * @param string $key
	 * @param string $expire
	 * @return array
	 */
	public static function fileGetSerialized($key, $expire=GDO_MEMCACHE_TTL)
	{
		if ($str = self::fileGet($key, $expire))
		{
			return unserialize($str);
		}
		return false;
	}
	
	/**
	 * Get cached content from the file system.
	 * @param string $key
	 * @param int $expire
	 * @return string|boolean
	 */
	public static function fileGet(string $key, int $expire=GDO_MEMCACHE_TTL) : ?string
	{
	    if (self::fileHas($key, $expire))
	    {
		    $path = self::filePath($key);
	    	return file_get_contents($path);;
	    }
	    return null;
	}
	
	/**
	 * Flush the whole or part of the filecache.
	 * @param string|null $key
	 * @return boolean
	 */
	public static function fileFlush(string $key=null) : bool
	{
	    if ($key === null)
	    {
	    	return
	    		FileUtil::removeDir(GDO_TEMP_PATH.'cache/') &&
	    		FileUtil::createDir(GDO_TEMP_PATH.'cache/');
	    }
	    else
	    {
	        return unlink(self::filePath($key));
	    }
	}
	
	/**
	 * Get the path of a filecache entry.
	 * @param string $key
	 * @return string
	 */
	public static function filePath(string $key='') : string
	{
	    $domain = GDO_DOMAIN;
	    $version = Module_Core::GDO_REVISION;
	    return GDO_TEMP_PATH . "cache/{$domain}_{$version}/{$key}";
	}
	
}

# No memcached stub shim so it won't crash.
if (!class_exists('Memcached', false))
{
	require 'Memcached.php';
}

# Dynamic poisonable prefix
define('MEMCACHEPREFIX', GDO_DOMAIN.Module_Core::GDO_REVISION);

# Default filecache config
if (!defined('GDO_FILECACHE'))
{
    define('GDO_FILECACHE', 1);
}

define('GDO_TEMP_PATH', GDO_PATH . Application::$INSTANCE->isUnitTests() ? 'temp_test/' : 'temp/');
