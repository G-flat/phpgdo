<?php
namespace GDO\Core;

use GDO\DB\Cache;
use GDO\DB\Database;
use GDO\DB\Query;
use GDO\DB\Result;
use GDO\Date\Time;
use GDO\Language\Trans;
use GDO\User\GDO_User;
use GDO\Util\Regex;

/**
 * A Data exchange object.
 * With fields, which values are backed by a database and caches.
 * Values are stored in the $gdoVars array.
 * When a GDT column is used, the $gdoVars are reference-copied into the GDT,
 * which make this framework tick fast with a low memory footprint.
 * It safes memory to only keep the GDTs once per Table.
 * Please note that all vars are considered string in GDO6, the db representation.
 * The values, like a datetime, are generated by GDT->toValue()
 * 
 * @see GDT
 * @see Cache
 * @see Database
 * @see Query
 * 
 * @author gizmore@wechall.net
 * @version 7.0.0
 * @since 3.2.0
 * @license GDOv7-LICENSE
 */
abstract class GDO extends GDT
{
	#################
	### Constants ###
	#################
	const TOKEN_LENGTH = 16; # length of gdoHashcode and GDT_Token
	
	const MYISAM = 'myisam'; # Faster writes
	const INNODB = 'innodb'; # Foreign keys
	const MEMORY = 'memory'; # Temp tables @TODO Temp memory tables not working? => remove
	
	##############
	### Static ###
	##############
	public static function table() : self { return Database::tableS(static::class); }

	#################
	### Construct ###
	#################
	public static $COUNT = 0;
	public function __construct()
	{
		self::$COUNT++;
		if (GDO_GDT_DEBUG)
		{
			self::logDebug();
		}
	}
	
	public function __wakeup()
	{
		self::$COUNT++;
		$this->recache = false;
		if (GDO_GDT_DEBUG)
		{
			self::logDebug();
		}
	}
	
	private static function logDebug()
	{
		Logger::log('gdo', sprintf('%d: %s', self::$COUNT, self::gdoClassNameS()));
		if (GDO_GDT_DEBUG >= 2)
		{
			Logger::log('gdo', Debug::backtrace('Backtrace', false));
		}
	}
	
	################
	### Abstract ###
	################
	/**
	 * @return GDT[]
	 */
	public abstract function gdoColumns() : array;
	
	/**
	 * Is this GDO backed by the GDO process cache?
	 * @return bool
	 */
	public function gdoCached() : bool { return true; }

	/**
	 * Is this GDO backed by the Memcached cache?
	 * @return bool
	 */
	public function memCached() : bool { return $this->gdoCached() && GDO_MEMCACHE; }

	/**
	 * Is this GDO backed by any cache means?
	 * @return bool
	 */
	public function cached() : bool { return $this->gdoCached() || $this->memCached(); }
	
	/**
	 * Return the mysql storage engine for this gdo.
	 * @return string
	 */
	public function gdoEngine() : string { return self::INNODB; } # @see self::MYISAM
	
	/**
	 * Is this GDO abstract? Required for inheritance hacks.
	 * @return bool
	 */
	public function gdoAbstract() : bool { return false; }
	
	################
	### Escaping ###
	################
	public static function escapeIdentifierS(string $identifier) : string { return str_replace("`", "\\`", $identifier); }
	public static function quoteIdentifierS(string $identifier) : string { return "`" . self::escapeIdentifierS($identifier) . "`"; }
	public static function escapeSearchS(string $var) : string { return str_replace(['%', "'", '"'], ['\\%', "\\'", '\\"'], $var); }
	public static function escapeS(string $var) : string { return str_replace(['\\', "'", '"'], ['\\\\', '\\\'', '\\"'], $var); }
	public static function quoteS($var) : string
	{
		if (is_string($var))
		{
			return '"' . self::escapeS($var) . '"';
		}
		elseif ($var === null)
		{
			return "NULL";
		}
		elseif (is_float($var) || is_int($var))
		{
			return "$var";
		}
		elseif (is_bool($var))
		{
			return $var ? '1' : '0';
		}
	}
	
	#################
	### Persisted ###
	#################
	private bool $persisted = false;
	public function isPersisted() : bool { return $this->persisted; }
	public function setPersisted(bool $persisted=true) : self
	{
		$this->id = null;
		$this->persisted = $persisted;
		return $this;
	}
	
	private bool $inited = false;
	public function isInited() : bool { return $this->inited; }
	public function setInited(bool $inited=true) : self { $this->inited = $inited; return $this; }
	
	########################
	### Custom temp vars ###
	########################
	/**
	 * @var mixed[]
	 */
	public array $temp;
	public function tempReset() { $this->temp = null; return $this; }
	public function tempGet($key) { return @$this->temp[$key]; }
	public function tempSet($key, $value)
	{
		if (!isset($this->temp))
		{
			$this->temp = [];
		}
		$this->temp[$key] = $value;
		return $this;
	}
	public function tempUnset($key) { unset($this->temp[$key]); return $this; }
	public function tempHas($key) { return isset($this->temp[$key]); }
	
	##############
	### Render ###
	##############
// 	public function display($key)
// 	{
// 		return html($this->gdoVars[$key]);
// 	}
	
// 	public function renderCLI()
// 	{
// 		return $this->getID() . '-' . $this->displayName();
// 	}
	
// 	public function renderChoice()
// 	{
// 		return $this->displayName();
// 	}
	
// 	public function renderJSON()
// 	{
// 		return $this->toJSON();
// 	}
	
// 	public function toJSON()
// 	{
// 		$values = [];
// 		foreach ($this->gdoColumnsCache() as $gdt)
// 		{
// 			if ($gdt->isSerializable())
// 			{
// 				if ($data = $gdt->gdo($this)->getGDOData())
// 				{
// 					foreach ($data as $k => $v)
// 					{
// 						$values[$k] = $v;
// 					}
// 				}
// 			}
// 		}
// 		return $values;
// 	}
	
	############
	### Vars ###
	############
	
	/**
	 * Mark vars as dirty.
	 * Either true for all, false for none, or an assoc array with field mappings.
	 * @var boolean,boolean[]
	 */
	private $dirty = false;
	
	/**
	 * Entity gdt vars.
	 * @var string[]
	 */
	private array $gdoVars;
	
	public function &getGDOVars() : array { return $this->gdoVars; }
	
	/**
	 * @param string $key
	 * @return bool
	 */
	public function hasVar($key) : bool
	{
		return array_key_exists($key, $this->gdoVars);
	}
	
	public function hasColumn($key) : bool
	{
		return array_key_exists($key, $this->gdoColumnsCache());
	}
	
	/**
	 * @param string $key
	 * @return string
	 */
	public function gdoVar($key) : string
	{
		if (isset($this->gdoVars[$key]))
		{
			return $this->gdoVars[$key];
		}
	}
	
	public function getVars(array $keys) : array
	{
		return array_combine($keys, array_map(function($key) {
			return $this->gdoVar($key);
		}, $keys));
	}
	
	/**
	 * @param string $key
	 * @param string $var
	 * @param boolean $markDirty
	 * @return self
	 */
	public function setVar($key, $var, $markDirty=true)
	{
		# @TODO: Better use temp? @see Vote/Up
		if (!$this->hasColumn($key))
		{
			$this->gdoVars[$key] = $var;
			return $this;
		}
		
		$gdt = $this->gdoColumn($key)->var($var);
		$d = false;
		if ($data = $gdt->getGDOData())
		{
			foreach ($data as $k => $v)
			{
				if ($this->gdoVars[$k] !== $v)
				{
					$this->gdoVars[$k] = $v === null ? null : (string)$v;
					$d = true;
				}
			}
		}
		return $markDirty && $d ? $this->markDirty($key) : $this;
	}
	
	public function setVars(array $vars=null, $markDirty=true)
	{
		foreach ($vars as $key => $value)
		{
			$this->setVar($key, $value, $markDirty);
		}
		return $this;
	}
	
	public function setValue($key, $value, $markDirty=true)
	{
		if ($vars = $this->gdoColumn($key)->value($value)->getGDOData())
		{
			$this->setVars($vars, $markDirty);
		}
		return $this;
	}
	
	public function setGDOVars(array $vars, $dirty=false)
	{
		$this->id = null;
		$this->gdoVars = $vars;
		return $this->dirty($dirty);
	}
	
	/**
	 * Get the gdo value of a column.
	 * @param string $key
	 * @return mixed
	 */
	public function gdoValue($key)
	{
		return $this->gdoColumn($key)->getValue();
	}
	
	#############
	### Dirty ###
	#############
	public function markClean($key)
	{
		if ($this->dirty === false)
		{
			$this->dirty = array_keys($this->gdoVars);
			unset($this->dirty[$key]);
		}
		elseif (is_array($this->dirty))
		{
			unset($this->dirty[$key]);
		}
		return $this;
	}
	
	public function markDirty($key)
	{
		if ($this->dirty === false)
		{
			$this->dirty = [];
		}
		if ($this->dirty !== true)
		{
			$this->dirty[$key] = true;
		}
		return $this;
	}
	
	public function isDirty()
	{
		return is_bool($this->dirty) ? $this->dirty : count($this->dirty) > 0;
	}
	
	/**
	 * Get gdoVars that have been changed.
	 * @return string[]
	 */
	public function getDirtyVars()
	{
		if ($this->dirty === true)
		{
			$vars = [];
			foreach ($this->gdoColumnsCache() as $gdt)
			{
				if ($data = $gdt->gdo($this)->getGDOData())
				{
					foreach ($data as $k => $v)
					{
						$vars[$k] = $v;
					}
				}
			}
			return $vars;
		}
		elseif ($this->dirty === false)
		{
			return [];
		}
		else
		{
			$vars = [];
			foreach (array_keys($this->dirty) as $name)
			{
				if ($data = $this->gdoColumn($name)->getGDOData())
				{
					foreach ($data as $k => $v)
					{
						$vars[$k] = $v;
					}
				}
			}
			return $vars;
		}
	}
	
	###############
	### Columns ###
	###############
	/**
	 * Get the first primary key column
	 * @return GDT
	 */
	public function gdoPrimaryKeyColumn()
	{
		foreach ($this->gdoColumnsCache() as $column)
		{
			if ($column->isPrimary())
			{
				return $column;
			}
		}
	}
	
	/**
	 * Get the primary key columns for a table.
	 * @return GDT[]
	 */
	public function gdoPrimaryKeyColumns()
	{
		$cache = self::table()->cache;
		
		if (isset($cache->pkColumns))
		{
			return $cache->pkColumns;
		}
		
		$columns = [];
		foreach ($this->gdoColumnsCache() as $column)
		{
			if ($column->isPrimary())
			{
				$columns[$column->name] = $column;
			}
			else
			{
				break; # early break is possible because we start all tables with their PKs.
			}
		}
		
		if (empty($columns))
		{
			$columns = $this->gdoColumnsCache();
		}
		
		$cache->pkColumns = $columns;
		
		return $columns;
	}
	
	public function gdoPrimaryKeyValues()
	{
		$values = [];
		foreach ($this->gdoPrimaryKeyColumns() as $gdt)
		{
			$values[$gdt->name] = $this->gdoVar($gdt->name);
		}
		return $values;
	}
	
	/**
	 * Get primary key column names.
	 * @return string[]
	 */
	public function gdoPrimaryKeyColumnNames()
	{
		$cache = self::table()->cache;
		
		if (isset($cache->pkNames))
		{
			return $cache->pkNames;
		}
		
		$names = [];
		foreach ($this->gdoColumnsCache() as $column)
		{
			if ($column->isPrimary())
			{
				$names[] = $column->name;
			}
			else
			{
				break; # Assume PKs are first until no more PKs
			}
		}
		
		if (empty($names))
		{
			$names = array_map(function(GDT $gdt){ return $gdt->name; }, $this->gdoColumnsCache());
		}
		
		$cache->pkNames = $names;
		
		return $names;
	}
	
	/**
	 * Get the first column of a specified GDT.
	 * Useful to make GDTs more automated. E.g. The auto inc column syncs itself on gdoAfterCreate.
	 *
	 * @param string $className
	 * @return \GDO\Core\GDT
	 */
	public function gdoColumnOf($className)
	{
		foreach ($this->gdoColumnsCache() as $gdt)
		{
			if (is_a($gdt, $className, true))
			{
				return $gdt->gdo($this);
			}
		}
	}
	
	public function gdoVarOf($className)
	{
		return $this->gdoVar($this->gdoColumnOf($className)->name);
	}
	
	public function gdoValueOf($className)
	{
		return $this->getValue($this->gdoColumnOf($className)->name);
	}
	
	/**
	 * Get the GDOs AutoIncrement column, if any.
	 * @return GDT_AutoInc
	 */
	public function gdoAutoIncColumn() { return $this->gdoColumnOf(GDT_AutoInc::class); }
	
	/**
	 * Get the GDOs name identifier column, if any.
	 * @return GDT_Name
	 */
	public function gdoNameColumn() { return $this->gdoColumnOf(GDT_Name::class); }
	
	/**
	 * Get the GDT column for a key.
	 * @param string $key
	 * @return GDT
	 */
	public function gdoColumn($key, $throw=true)
	{
		/** @var $gdt GDT **/
		if ($gdt = $this->gdoColumnsCache()[$key])
		{
			return $gdt->gdo($this);
		}
		elseif ($throw)
		{
			throw new GDO_Error('err_unknown_gdo_column', [$this->displayName(), html($key)]);
		}
	}
	
	/**
	 * Get a copy of a GDT column.
	 * @param string $key
	 * @return GDT
	 */
	public function gdoColumnCopy($key)
	{
		/** @var $column GDT **/
		$column = clone $this->gdoColumnsCache()[$key];
		return $column->gdo($this);#->var($column->initial);
	}
	
	/**
	 * Get all GDT columns except those listed.
	 * @param string[] ...$except
	 * @return GDT[]
	 */
	public function gdoColumnsExcept(...$except)
	{
		$columns = array();
		foreach (array_keys($this->gdoColumnsCache()) as $key)
		{
			if (!in_array($key, $except, true))
			{
				$columns[$key] = $this->gdoColumn($key);
			}
		}
		return $columns;
	}
	
	//     /**
	//      * Get a copy of all GDT columns except those listed. Slow.
	//      * Used in MethodCRUD because some GDO have not correct fields in gdoColumns().
	//      *
	//      * @param string[] ...$except
	//      * @return GDT[]
	//      */
	//     public function gdoColumnsCopyExcept(...$except)
	//     {
	//         $columns = array();
	//         foreach (array_keys($this->gdoColumnsCache()) as $key)
		//         {
		//             if (!in_array($key, $except, true))
			//             {
			//                 $columns[$key] = $this->gdoColumnCopy($key);
			//             }
		//         }
	//         return $columns;
	//     }
	
	//     /**
	//      * Get a copy of multiple gdt.
	//      * @param string[] ...$names
	//      * @return GDT[]
	//      */
	//     public function gdoColumnsCopy(...$names)
	//     {
	//         $columns = array();
	//         foreach (array_keys($this->gdoColumnsCache()) as $key)
		//         {
		//             if (in_array($key, $names, true))
			//             {
			//                 $columns[$key] = $this->gdoColumnCopy($key);
			//             }
		//         }
	//         return $columns;
	//     }
	
	##########
	### DB ###
	##########
	/**
	 * Create a new query for this GDO table.
	 * @return \GDO\DB\Query
	 */
	public function query()
	{
		return new Query(self::table());
	}
	
	/**
	 * Find a row by AutoInc Id.
	 * @param string $id
	 * @return static
	 */
	public function find($id=null, $exception=true)
	{
		if ($id && ($gdo = $this->getById($id)))
		{
			return $gdo;
		}
		if ($exception)
		{
			self::notFoundException(html($id));
		}
	}
	
	public function findCached(...$ids)
	{
		if (!($gdo = $this->table()->cache->findCached(...$ids)))
		{
			$gdo = self::getById(...$ids);
		}
		return $gdo;
	}
	
	/**
	 * @param string $where
	 * @return string
	 */
	public function countWhere($condition='true')
	{
		return $this->select('COUNT(*)', false)->where($condition)->
		noOrder()->exec()->fetchValue();
	}
	
	/**
	 * Find a row by condition. Throws GDO::notFoundException.
	 * @param string $where
	 * @return self
	 */
	public function findWhere($condition)
	{
		if (!($gdo = $this->getWhere($condition)))
		{
			self::notFoundException(html($condition));
		}
		return $gdo;
	}
	
	/**
	 * Get a row by condition.
	 * @param string $condition
	 * @return self
	 */
	public function getWhere($condition)
	{
		return $this->select()->where($condition)->
		first()->exec()->fetchObject();
	}
	
	/**
	 * @param string $columns
	 * @return \GDO\DB\Query
	 */
	public function select($columns='*', $withHooks=true)
	{
		$query = $this->query()->select($columns)->from($this->gdoTableIdentifier());
		if ($withHooks)
		{
			$this->beforeRead($query);
		}
		return $query;
	}
	
	################
	### Validate ###
	################
	public function isValid()
	{
		$invalid = 0;
		foreach ($this->gdoColumnsCache() as $gdt)
		{
			$gdt = $this->gdoColumn($gdt->name); # assign me as gdo to gdt
			$invalid += $gdt->validate($gdt->getValue()) ? 0 : 1;
			//             $error = $gdt->error;
			//             if ($error)
				//             {
				//                 echo $gdt->name;
				//             }
		}
		return $invalid === 0;
	}
	
	##############
	### Delete ###
	##############
	/**
	 * Delete this entity.
	 * @param boolean $withHooks
	 * @return self
	 */
	public function delete($withHooks=true)
	{
		return $this->deleteB($withHooks);
	}
	
	/**
	 * Check if we are deleted.
	 * @return boolean
	 */
	public function isDeleted()
	{
		if ($gdt = $this->gdoColumnOf(GDT_DeletedAt::class))
		{
			return $gdt->getVar() !== null;
		}
		if ($gdt = $this->gdoColumnOf(GDT_DeletedBy::class))
		{
			return $gdt->getVar() !== null;
		}
		return $this->isPersisted();
	}
	
	/**
	 * Mark this GDO as deleted, or delete physically.
	 * @param boolean $withHooks
	 * @return self
	 */
	public function markDeleted($withHooks=true)
	{
		if ($gdt = $this->gdoColumnOf(GDT_DeletedAt::class))
		{
			$this->setVar($gdt->name, Time::getDate());
			$change = true;
		}
		if ($gdt = $this->gdoColumnOf(GDT_DeletedBy::class))
		{
			$this->setVar($gdt->name, GDO_User::current()->getID());
			$change = true;
		}
		if ($change)
		{
			$this->save($withHooks);
			if ($withHooks)
			{
				$this->afterDelete();
			}
		}
		else
		{
			return $this->deleteB($withHooks);
		}
	}
	
	/**
	 * Delete multiple rows, but still one by one to trigger all events correctly.
	 * @param string $condition
	 * @return int number of deleted rows
	 */
	public function deleteWhere($condition, $withHooks=true)
	{
		$deleted = 0;
		if ($withHooks)
		{
			$result = $this->table()->select()->where($condition)->exec();
			while ($gdo = $result->fetchObject())
			{
				$deleted++;
				$gdo->deleteB();
			}
		}
		else
		{
			if ($this->query()->
			delete($this->gdoTableIdentifier())->
			where($condition)->exec())
			{
				$deleted = Database::instance()->affectedRows();
			}
		}
		return $deleted;
	}
	
	private function deleteB($withHooks=true)
	{
		if ($this->persisted)
		{
			$query = $this->query()->delete($this->gdoTableIdentifier())->where($this->getPKWhere());
			if ($withHooks)
			{
				$this->beforeDelete($query);
			}
			$query->exec();
			$this->persisted = false;
			if ($withHooks)
			{
				$this->afterDelete();
			}
			$this->uncache();
		}
		return $this;
	}
	
	###############
	### Replace ###
	###############
	public function insert($withHooks=true)
	{
		$query = $this->query()->
		insert($this->gdoTableIdentifier())->
		values($this->getDirtyVars());
		return $this->insertOrReplace($query, $withHooks);
	}
	
	public function replace($withHooks=true)
	{
		# Check for empty id.
		# Checking for $persisted is wrong, as replace rows can be constructed from scratch.
		$id = $this->getID();
		if ( (!$id) || preg_match('#^[:0]+$#D', $id) )
		{
			return $this->insert($withHooks);
		}
		
		$query = $this->query()->
		replace($this->gdoTableIdentifier())->
		values($this->gdoPrimaryKeyValues())->
		values($this->getDirtyVars());
		
		return $this->insertOrReplace($query, $withHooks);
	}
	
	private function insertOrReplace(Query $query, $withHooks)
	{
		if ($withHooks)
		{
			$this->beforeCreate($query);
		}
		$query->exec();
		$this->dirty = false;
		$this->persisted = true;
		if ($withHooks)
		{
			$this->afterCreate();
			$this->cache(); # not needed for new rows?
		}
		return $this;
	}
	
	##############
	### Update ###
	##############
	/**
	 * Build a generic update query for the whole table.
	 * @return Query
	 */
	public function update()
	{
		return $this->query()->update($this->gdoTableIdentifier());
	}
	
	/**
	 * @return Query
	 */
	public function deleteQuery()
	{
		return $this->query()->delete($this->gdoTableName());
	}
	
	/**
	 * Build an entity update query.
	 * @return Query
	 */
	public function updateQuery()
	{
		return $this->entityQuery()->update($this->gdoTableIdentifier());
	}
	
	/**
	 * Save this entity.
	 * @return self
	 */
	public function save($withHooks=true)
	{
		if (!$this->persisted)
		{
			return $this->insert($withHooks);
		}
		if ($setClause = $this->getSetClause())
		{
			$query = $this->updateQuery()->set($setClause);
			
			if ($withHooks)
			{
				$this->beforeUpdate($query);
			}
			
			$query->exec();
			
			$this->dirty = false;
			
			if ($withHooks)
			{
				$this->afterUpdate();
				$this->recache(); # save is the only action where we recache!
			}
		}
		return $this;
	}
	
	########################
	### Var manipulation ###
	########################
	public function increase($key, $by=1)
	{
		return $by == 0 ? $this : $this->saveVar($key, $this->gdoVar($key) + $by);
	}
	
	public function saveVar($key, $var, $withHooks=true, &$worthy=false)
	{
		return $this->saveVars([$key => $var], $withHooks, $worthy);
	}
	
	/**
	 * @param array $vars
	 * @param boolean $withHooks
	 * @param boolean $worthy
	 * @return GDO
	 */
	public function saveVars(array $vars, $withHooks=true, &$worthy=false)
	{
		$worthy = false; # Anything changed?
		$query = $this->updateQuery();
		foreach ($vars as $key => $var)
		{
			if (array_key_exists($key, $this->gdoVars))
			{
				if ($var !== $this->gdoVars[$key])
				{
					$query->set(sprintf("%s=%s", $key, self::quoteS($var)));
					$this->markClean($key);
					$worthy = true; # We got a change
				}
			}
		}
		
		# Call hooks even when not needed. Because its needed on GDT_Files
		if ($withHooks)
		{
			$this->beforeUpdate($query); # Can do trickery here... not needed?
		}
		
		if ($worthy)
		{
			$query->exec();
			foreach ($vars as $key => $var)
			{
				$this->gdoVars[$key] = $var;
			}
			if ($withHooks)
			{
				$this->recache();
			}
		}
		
		# Call hooks even when not needed. Because its needed on GDT_Files
		if ($withHooks)
		{
			$this->afterUpdate();
		}
		
		return $this;
	}
	
	public function saveValue($key, $value, $withHooks=true)
	{
		$var = $this->gdoColumn($key)->toVar($value);
		return $this->saveVar($key, $var, $withHooks);
	}
	
	public function saveValues(array $values, $withHooks=true)
	{
		$vars = [];
		foreach ($values as $key => $value)
		{
			$this->gdoColumn($key)->setGDOValue($value);
			$vars[$key] = $this->gdoVar($key);
		}
		return $this->saveVars($vars, $withHooks);
	}
	
	/**
	 * @return \GDO\DB\Query
	 */
	public function entityQuery()
	{
		if (!$this->persisted)
		{
			throw new GDO_Error('err_save_unpersisted_entity', [$this->gdoClassName()]);
		}
		return $this->query()->where($this->getPKWhere());
	}
	
	public function getSetClause()
	{
		$setClause = '';
		if ($this->dirty !== false)
		{
			foreach ($this->gdoColumnsCache() as $column)
			{
				if (!$column->virtual)
				{
					if ( ($this->dirty === true) || (isset($this->dirty[$column->name])) )
					{
						foreach ($column->gdo($this)->getGDOData() as $k => $v)
						{
							if ($setClause !== '')
							{
								$setClause .= ',';
							}
							$setClause .= $k . '=' . self::quoteS($v);
						}
					}
				}
			}
		}
		return $setClause;
	}
	
	####################
	### Primary Keys ###
	####################
	/**
	 * Get the primary key where condition for this row.
	 * @return string
	 */
	public function getPKWhere()
	{
		$where = "";
		foreach ($this->gdoPrimaryKeyColumns() as $column)
		{
			if ($where)
			{
				$where .= ' AND ';
			}
			$where .= $column->identifier() . ' = ' . $this->quoted($column->name);
		}
		return $where;
	}
	
	public function quoted($key) { return self::quoteS($this->gdoVar($key)); }
	
	################
	### Instance ###
	################
	/**
	 * @param array $gdoVars
	 * @return self
	 */
	public static function entity(array $gdoVars)
	{
		$instance = new static();
		$instance->gdoVars = $gdoVars;
		return $instance;
	}
	
	/**
	 * Raw initial string data.
	 * @TODO throw error on unknown initial vars.
	 * @param array $initial data to copy
	 * @return array the new blank data1
	 */
	public static function blankData(array $initial = null)
	{
		$table = self::table();
		$gdoVars = [];
		foreach ($table->gdoColumnsCache() as $column)
		{
			# init gdt with initial var.
			if (isset($initial[$column->name]))
			{
				$var = $initial[$column->name];
				$column->var($var);
				//                 $column->var($column->inputToVar($var));
			}
			else
			{
				$column->var($column->initial);
			}
			
			# loop over blank data
			if ($data = $column->blankData())
			{
				foreach ($data as $k => $v)
				{
					if (isset($initial[$k]))
					{
						# override with initial
						$gdoVars[$k] = $initial[$k];
					}
					else
					{
						# Use blank data as is
						$gdoVars[$k] = $v;
					}
				}
			}
		}
		return $gdoVars;
	}
	
	/**
	 * Create a new entity instance.
	 * @return self
	 */
	public static function blank(array $initial = null) : self
	{
		return self::entity(self::blankData($initial))->dirty()->setPersisted(false);
	}
	
	public function dirty($dirty=true) : self
	{
		$this->dirty = $dirty;
		return $this;
	}
	
	##############
	### Get ID ###
	##############
	private string $id;
	/**
	 * Id cache
	 * @var $id string
	 */
	public function getID() : string
	{
		if ($this->id)
		{
			return $this->id;
		}
		$id = '';
		foreach ($this->gdoPrimaryKeyColumnNames() as $name)
		{
			$id2 = $this->gdoVar($name);
			$id = $id ? "{$id}:{$id2}" : $id2;
		}
		$this->id = $id;
		return $id;
	}
	
	/**
	 * Display a translated table name with ID.
	 * Or the first GDT_Name column.
	 *
	 * @see Trans
	 * @see WithName
	 * @see GDT_Name
	 * @return string
	 */
	public function displayName() : string
	{
		# 1st check if we are the table
		if ($this->isTable)
		{
			return $this->gdoHumanName();
		}
		
		# 2nd attempt GDT_Name column
		if ($this->table()->isInited())
		{
			if ($gdt = $this->gdoColumnOf(GDT_Name::class))
			{
				return $this->display($gdt->name);
			}
		}
		
		# fallback to Name#Id
		return $this->gdoHumanName() . "#" . $this->getID();
	}
	
	##############
	### Get by ###
	##############
	/**
	 * Get a row by a single arbritary column value.
	 * @param string $key
	 * @param string $value
	 * @return self
	 */
	public static function getBy(string $key, string $var) : self
	{
		return self::table()->getWhere($key . '=' . self::quoteS($var));
	}
	
	/**
	 * Get a row by a single column value.
	 * Throw exception if not found.
	 * @param string $key
	 * @param string $value
	 * @return self
	 */
	public static function findBy(string $key, string $var) : self
	{
		if (!($gdo = self::getBy($key, $var)))
		{
			self::notFoundException($var);
		}
		return $gdo;
	}
	
	/**
	 * @param array $vars
	 * @return self
	 */
	public static function getByVars(array $vars)
	{
		$query = self::table()->select();
		foreach ($vars as $key => $value)
		{
			$query->where(self::quoteIdentifierS($key) . '=' . self::quoteS($value));
		}
		return $query->first()->exec()->fetchObject();
	}
	
	/**
	 * Get a row by auto inc column.
	 * @param string ...$id
	 * @return self
	 */
	public static function getById(string...$id)
	{
		$table = self::table();
		if ( (!$table->cached()) || (!($object = $table->cache->findCached(...$id))) )
		{
			$i = 0;
			$query = $table->select();
			foreach ($table->gdoPrimaryKeyColumns() as $column)
			{
				$condition = $table->gdoTableName() . '.' . $column->identifier() .
				'=' . self::quoteS($id[$i++]);
				$query->where($condition);
			}
			$object = $query->first()->exec()->fetchObject();
		}
		return $object;
	}
	
	/**
	 * @param string ...$id
	 * @return self
	 */
	public static function findById(string...$id)
	{
		if ($object = self::getById(...$id))
		{
			return $object;
		}
		self::notFoundException(implode(':', $id));
	}
	
	public static function findByGID(string $id)
	{
		return self::findById(...explode(':', $id));
	}
	
	public static function notFoundException(string $id)
	{
		throw new GDO_Error('err_gdo_not_found', [self::table()->gdoHumanName(), html($id)]);
	}
	
	/**
	 * Fetch from result set as this table.
	 * @param Result $result
	 * @return self
	 */
	public function fetch(Result $result)
	{
		return $result->fetchAs($this);
	}
	
	public function fetchAll(Result $result)
	{
		$back = [];
		while ($gdo = $this->fetch($result))
		{
			$back[] = $gdo;
		}
		return $back;
	}
	
	#############
	### Cache ###
	#############
	public Cache $cache;
	
	public function initCache() : void { $this->cache = new Cache($this); }
	
	public function initCached(array $row, bool $useCache=true) : self
	{
		return $this->memCached() ?
			$this->cache->initGDOMemcached($row, $useCache) :
			$this->cache->initCached($row, $useCache);
	}
	
	public function gkey() : string
	{
		$gkey = self::table()->cache->tableName . $this->getID();
		return $gkey;
	}
	
	public function reload($id) : self
	{
		$table = self::table();
		if ($table->cached() && $table->cache->hasID($id))
		{
			$i = 0;
			$id = explode(':', $id);
			$query = $this->select();
			foreach ($this->gdoPrimaryKeyColumns() as $column)
			{
				$query->where($column->identifier() . '=' . self::quoteS($id[$i++]));
			}
			$object = $query->uncached()->first()->exec()->fetchObject();
			return $object ? $table->cache->recache($object) : null;
		}
	}
	
	/**
	 * This function triggers a recache, also over IPC, if IPC is enabled.
	 */
	public function recache() : void
	{
		if ($this->cached())
		{
			self::table()->cache->recache($this);
		}
	}
	
	public function recacheMemcached() : void
	{
		if ($this->memCached())
		{
			$this->table()->cache->recache($this);
		}
	}
	
	public $recache = false;
	public function recaching() : self
	{
		$this->recache = true;
		return $this;
	}
	
	public function cache() : void
	{
		if ($this->cached())
		{
			self::table()->cache->recache($this);
		}
	}
	
// 	/**
// 	 * @deprecated Untested and why does it exist?
// 	 */
// 	public function uncache() : void
// 	{
// 		if ($this->table()->cache)
// 		{
// 			$this->table()->cache->uncache($this);
// 		}
// 	}
	
	public function clearCache() : self
	{
		if ($this->cached())
		{
			$cache = self::table()->cache;
			$cache->clearCache();
			Cache::flush(); # @TODO Find a way to only remove memcached entries for this single GDO.
		}
		return $this;
	}
	
	###########
	### All ###
	###########
	/**
	 * @return self[]
	 */
	public function &all($order=null, $json=false) : array
	{
		$order = $order ? $order : $this->gdoPrimaryKeyColumn()->name;
		return self::allWhere('true', $order, $json);
	}
	
	/**
	 * @return self[]
	 */
	public function &allWhere($condition='true', $order=null, $json=false) : array
	{
		return self::table()->select()->
		where($condition)->order($order)->
		exec()->fetchAllArray2dObject(null, $json);
	}
	
	public function uncacheAll() : self
	{
		$this->table()->cache->all = null;
		Cache::remove($this->cacheAllKey());
		return $this;
	}
	
	public function cacheAllKey() : string
	{
		return 'all_' . $this->gdoTableName();
	}
	
	public function allCachedExpiration(int $expire=GDO_MEMCACHE_TTL) : void
	{
		$this->cache->expiration = $expire;
	}
	
	/**
	 * Get all rows via allcache.
	 * @param string $order
	 * @param boolean $asc
	 * @return self[]
	 */
	public function &allCached($order=null, $json=false) : array
	{
		if ($this->cached())
		{
			# Already cached
			$cache = self::table()->cache;
			if (isset($cache->all))
			{
				return $cache->all;
			}
		}
		else
		{
			# No caching at all
			return $this->select()->order($order)->exec()->fetchAllArray2dObject(null, $json);
		}
		
		if (!$this->memCached())
		{
			# GDO cached
			$all = $this->select()->order($order)->exec()->fetchAllArray2dObject(null, $json);
			$cache->all = $all;
			return $all;
		}
		else
		{
			# Memcached
			$key = $this->cacheAllKey();
			if (false === ($all = Cache::get($key)))
			{
				$all = $this->select()->order($order)->exec()->fetchAllArray2dObject(null, $json);
				Cache::set($key, $all);
			}
			$cache->all = $all;
			return $all;
		}
	}
	
	public function removeAllCache() : void
	{
		$key = 'all_' . $this->gdoTableName();
		Cache::remove($key);
	}
	
	###########################
	###  Table manipulation ###
	###########################
	/**
	 * @param string $className
	 * @return self
	 */
	public static function tableFor(string $className) : self { return Database::tableS($className); }
	
	public bool $isTable = false;
	public function gdoIsTable() : bool { return $this->isTable; }
	public function gdoTableName() : string { return $this->table()->cache->tableName; }
	public function gdoTableIdentifier() : string { return $this->gdoTableName(); }
	
	public function createTable(bool $reinstall=false) : bool
	{
		if (!($db = Database::instance()))
		{
			die('gdo database not configured!');
		}
		return !!$db->createTable($this);
	}
	public function dropTable() : bool { return !!Database::instance()->dropTable($this); }
	public function truncate() : bool { return !!Database::instance()->truncateTable($this); }
	
	/**
	 * @return GDT[]
	 */
	public function &gdoColumnsCache() : array { return Database::columnsS(static::class); }
	
	/**
	 * @return GDT[]
	 */
	public function getGDOColumns(array $names) : array
	{
		$columns = [];
		foreach ($names as $key)
		{
			$columns[$key] = $this->gdoColumn($key);
		}
		return $columns;
	}
	
	##############
	### Events ###
	##############
	private function beforeCreate(Query $query) : void
	{
		foreach ($this->gdoColumnsCache() as $gdt)
		{
			$gdt->gdo($this)->gdoBeforeCreate($query);
		}
		$this->gdoBeforeCreate();
	}
	
	private function beforeRead(Query $query) : void
	{
		foreach ($this->gdoColumnsCache() as $gdt)
		{
			$gdt->gdoBeforeRead($query);
		}
		$this->gdoBeforeRead();
	}
	
	private function beforeUpdate(Query $query) : void
	{
		foreach ($this->gdoColumnsCache() as $gdt)
		{
			$gdt->gdo($this)->gdoBeforeUpdate($query);
		}
		$this->gdoBeforeUpdate();
	}
	
	private function beforeDelete(Query $query) : void
	{
		foreach ($this->gdoColumnsCache() as $gdt)
		{
			$gdt->gdo($this)->gdoBeforeDelete($query);
		}
		$this->gdoBeforeDelete();
	}
	
	public function afterCreate() : void
	{
		# Flags
		$this->dirty = false;
		$this->setPersisted();
		# Trigger event for AutoCol, EditedAt, EditedBy, etc.
		foreach ($this->gdoColumnsCache() as $gdt)
		{
			$gdt->gdo($this)->gdoAfterCreate();
		}
		$this->gdoAfterCreate();
	}
	
	public function afterRead() : void
	{
		foreach ($this->gdoColumnsCache() as $gdt)
		{
			$gdt->gdo($this)->gdoAfterRead();
		}
		$this->gdoAfterRead();
	}
	
	public function afterUpdate() : void
	{
		# Flags
		$this->dirty = false;
		# Trigger event for AutoCol, EditedAt, EditedBy, etc.
		foreach ($this->gdoColumnsCache() as $gdt)
		{
			$gdt->gdo($this)->gdoAfterUpdate();
		}
		$this->gdoAfterUpdate();
	}
	
	public function afterDelete() : void
	{
		# Flags
		$this->dirty = false;
		$this->persisted = false;
		# Trigger events on GDTs.
		foreach ($this->gdoColumnsCache() as $gdt)
		{
			$gdt->gdo($this)->gdoAfterDelete();
		}
		$this->gdoAfterDelete();
	}
	
	# Overrides
	public function gdoBeforeCreate() : void {}
	public function gdoBeforeRead() : void {}
	public function gdoBeforeUpdate() : void {}
	public function gdoBeforeDelete() : void {}
	
	public function gdoAfterCreate() : void {}
	public function gdoAfterRead() : void {}
	public function gdoAfterUpdate() : void {}
	public function gdoAfterDelete() : void {}
	
	################
	### Hashcode ###
	################
	/**
	 * Generate a hashcode from gdo vars.
	 * This is often used in approval tokens or similar.
	 * @return string
	 */
	public function gdoHashcode() : string
	{
		return self::gdoHashcodeS($this->gdoVars);
	}
	
	/**
	 * Generate a hashcode from an associative array.
	 * @param array $gdoVars
	 * @return string
	 */
	public static function gdoHashcodeS(array $gdoVars) : string
	{
		return substr(sha1(GDO_SALT.json_encode($gdoVars)), 0, self::TOKEN_LENGTH);
	}
	
	##############
	### Render ###
	##############
	
	###############
	### Sorting ###
	###############
	/**
	 * Sort GDO[] by a field.
	 * @param GDO[] $array
	 * @param string $columnName
	 * @param bool $ascending
	 */
	public function sort(array &$array, string $columnName, bool $ascending=true)
	{
		return $this->gdoColumn($columnName)->sort($array, $ascending);
	}
	
	#############
	### Order ###
	#############
	public function getDefaultOrder()
	{
		foreach ($this->gdoColumnsCache() as $gdt)
		{
			if ($gdt->orderable)
			{
				return $gdt->name;
			}
		}
	}
	
	#######################
	### Bulk Operations ###
	#######################
	/**
	 * Mass insertion.
	 * @param GDT[] $fields
	 * @param array $data
	 */
	public static function bulkReplace(array $fields, array $data, $chunkSize=100)
	{
		self::bulkInsert($fields, $data, $chunkSize, 'REPLACE');
	}
	
	public static function bulkInsert(array $fields, array $data, $chunkSize=100, $insert='INSERT')
	{
		foreach (array_chunk($data, $chunkSize) as $chunk)
		{
			self::_bulkInsert($fields, $chunk, $insert);
		}
	}
	
	private static function _bulkInsert(array $fields, array $data, string $insert='INSERT') : bool
	{
		$names = [];
		$table = self::table();
		foreach ($fields as $field)
		{
			$names[] = $field->name;
		}
		$names = implode('`, `', $names);
		
		$values = [];
		foreach ($data as $row)
		{
			$brow = [];
			foreach ($row as $col)
			{
				$brow[] = self::quoteS($col);
			}
			$values[] = implode(',', $brow);
		}
		$values = implode("),\n(", $values);
		
		$query = "$insert INTO {$table->gdoTableIdentifier()} (`$names`)\n VALUES\n($values)";
		Database::instance()->queryWrite($query);
		return true;
	}
	
	############
	### Lock ###
	############
	public function lock(string $lock, int $timeout=10) : bool
	{
		$result = Database::instance()->lock($lock, $timeout);
		return mysqli_fetch_field($result) === '1';
	}
	
	public function unlock($lock) : bool
	{
		$result = Database::instance()->unlock($lock);
		return mysqli_fetch_field($result) === '1';
	}
	
	##############
	### Module ###
	##############
	/**
	 * Get the module for a gdo.
	 * @return GDO_Module
	 */
	public function getModule() : GDO_Module
	{
		$name = $this->getModuleName();
		return ModuleLoader::instance()->getModule($name);
	}
	
	public function getModuleName() : string
	{
		$klass = get_class($this);
		return Regex::firstMatch('/^GDO\\\\([^\\\\]+)\\\\/', $klass);
	}
	
}
