<?php
namespace GDO\Core;

use GDO\DB\Cache;
use GDO\DB\Database;
use GDO\DB\Query;
use GDO\DB\Result;
use GDO\Date\Time;
use GDO\User\GDO_User;

/**
 * A data exchange object?.
 * 
 * A GDO is both, a table and an entity.
 * The table gdo just holds the caches and GDT instances.
 * The other entitites have $gdoVars. A DB row.
 * 
 * An array of db vars, which values are backed by a database and caches.
 * DB vars are stored in the $gdoVars array.
 * When a GDT column is used, the $gdoVars are reference-copied into the GDT,
 * which make this framework tick fast with a low memory footprint.
 * It safes memory to only keep the GDTs once per Table.
 * Please note that all vars are considered string in GDOv7, the db representation.
 * The values, like a datetime, are generated by GDT->toValue()
 * 
 * - Offers bulk operations
 * 
 * @author gizmore@wechall.net
 * @license GDOv7-LICENSE
 * @version 7.0.0
 * @since 3.2.0
 * @see GDT
 * @see Cache
 * @see Database
 * @see Query
 * @see Result
 * @see WithTemp
 * @see WithModule
 */
abstract class GDO extends GDT
{
	use WithTemp;
	use WithModule;
	
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
	/**
	 * Get the table GDO for this class.
	 * @return self
	 */
	public static function table() : self
	{
		return Database::tableS(static::class);
	}

	#################
	### Construct ###
	#################
	public static int $GDO_COUNT = 0; # total allocs
	public static int $GDO_KILLS = 0; # total deallocs
	public static int $GDO_PEAKS = 0; # max sim. alive
	
	public function __construct()
	{
// 		parent::__construct(); # DO NOT! call GDT perf counter!
		$this->afterLoaded();
	}
	
	public function __wakeup()
	{
		$this->recache = false;
		$this->afterLoaded();
	}
	
	public function __destruct()
	{
		self::$GDO_KILLS++;
	}
	
	private function afterLoaded() : void
	{
		self::$GDO_COUNT++;
		$alive = self::$GDT_COUNT - self::$GDT_KILLS;
		if ($alive > self::$GDT_PEAKS)
		{
			self::$GDT_PEAKS = $alive;
		}
		if (GDO_GDT_DEBUG)
		{
			self::logDebug();
		}
	}
	
	private static function logDebug() : void
	{
		Logger::log('gdo', sprintf('%d: %s', self::$GDO_COUNT, self::gdoClassNameS()));
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
// 	public static function quoteIdentifierS(string $identifier) : string { return "`" . self::escapeIdentifierS($identifier) . "`"; }
	public static function quoteIdentifierS(string $identifier) : string { return $identifier; } # performance for features
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
		elseif (is_numeric($var))
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
		unset($this->id);
		$this->persisted = $persisted;
		return $this;
	}
	
// 	private bool $inited = false;
// 	public function isInited() : bool { return $this->inited; }
// 	public function setInited(bool $inited=true) : self { $this->inited = $inited; return $this; }
	
	##############
	### Render ###
	##############
	public function renderName() : string
	{
		return $this->gdoHumanName() . "#" . $this->getID();
	}
	
// 	public function display($key)
// 	{
// 		return html($this->gdoVars[$key]);
// 	}
	
// 	public function renderCLI()
// 	{
// 		return $this->getID() . '-' . $this->renderName();
// 	}
	
	public function renderChoice() : string
	{
		return $this->renderName();
	}
	
	public function renderJSON()
	{
		return $this->toJSON();
	}
	
	public function renderCLI() : string
	{
		return $this->renderName();
	}
	
	/**
	 * @return GDT[]
	 */
	public function toJSON()
	{
		$values = [];
		foreach ($this->gdoColumnsCache() as $gdt)
		{
			if ($gdt->isSerializable())
			{
				if ($data = $gdt->gdo($this)->getGDOData())
				{
					foreach ($data as $k => $v)
					{
						if ($v !== null)
						{
							$values[$k] = $v;
						}
					}
				}
			}
		}
		return $values;
	}
	
	############
	### Vars ###
	############
	/**
	 * Mark vars as dirty.
	 * Either true for all, false for none, or an assoc array with field mappings.
	 * @var mixed[] $dirty
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
	public function gdoVar(string $key) : ?string
	{
		return isset($this->gdoVars[$key]) ? $this->gdoVars[$key] : null;
	}
	
	public function gdoVars(array $keys) : array
	{
		return array_combine($keys, array_map(function($key) {
			return $this->gdoVar($key);
		}, $keys));
	}
	
	/**
	 * Break these GDT functions as they confuse you now.
	 */
	public function getVar()
	{
		throw new GDO_Error('err_gdo_no_gdt', ['getVar', $this->gdoHumanName()]);
	}
	
	/**
	 * Break these GDT functions as they confuse you now.
	 */
	public function getValue(string $var = null)
	{
		throw new GDO_Error('err_gdo_no_gdt', ['getValue', $this->gdoHumanName()]);
	}
	
/**
	 * @param string $key
	 * @param string $var
	 * @param boolean $markDirty
	 * @return self
	 */
	public function setVar(string $key, string $var=null, bool $markDirty=true) : self
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
		return ($markDirty && $d) ? $this->markDirty($key) : $this;
	}
	
	public function setVars(array $vars=null, $markDirty=true) : self
	{
		foreach ($vars as $key => $value)
		{
			$this->setVar($key, $value, $markDirty);
		}
		return $this;
	}
	
	public function setValue(string $key, $value, bool $markDirty=true) : self
	{
		if ($vars = $this->gdoColumn($key)->value($value)->getGDOData())
		{
			$this->setVars($vars, $markDirty);
		}
		return $this;
	}
	
	public function setGDOVars(array $vars, $dirty=false) : self
	{
		unset($this->id);
		$this->gdoVars = $vars;
		return $this->dirty($dirty);
	}
	
	/**
	 * Get the gdo value of a column.
	 * @param string $key
	 * @return mixed
	 */
	public function gdoValue(string $key)
	{
		return $this->gdoColumn($key)->getValue();
	}
	
	#############
	### Dirty ###
	#############
	public function markClean(string $key) : self
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
	
	public function markDirty(string $key) : self
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
	
	public function isDirty() : bool
	{
		return is_bool($this->dirty) ? $this->dirty : (count($this->dirty) > 0);
	}
	
	/**
	 * Get gdoVars that have been changed.
	 * @return string[]
	 */
	public function getDirtyVars() : array
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
	public function gdoPrimaryKeyColumn() : ?GDT
	{
		foreach ($this->gdoColumnsCache() as $column)
		{
			if ($column->isPrimary())
			{
				return $column;
			}
		}
		return null;
	}
	
	/**
	 * Get the primary key columns for a table.
	 * @return GDT[]
	 */
	public function gdoPrimaryKeyColumns() : array
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
	
	public function gdoPrimaryKeyValues() : array
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
	public function gdoPrimaryKeyColumnNames() : array
	{
		$table = self::table();
		$cache = isset($table->cache) ? $table->cache : null;
		
		if ($cache && isset($cache->pkNames))
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
			$names = array_map(function(GDT $gdt){
				return $gdt->getName();
			}, $this->gdoColumnsCache());
		}
		
		if ($cache)
		{
			$cache->pkNames = $names;
		}
		
		return $names;
	}
	
	/**
	 * Get the first column of a specified GDT.
	 * Useful to make GDTs more automated. E.g. The auto inc column syncs itself on gdoAfterCreate.
	 *
	 * @param string $className
	 * @return \GDO\Core\GDT
	 */
	public function gdoColumnOf($className) : ?GDT
	{
		foreach ($this->gdoColumnsCache() as $gdt)
		{
			if (is_a($gdt, $className, true))
			{
				return $gdt->gdo($this);
			}
		}
		return null;
	}
	
	public function gdoVarOf($className) : ?string
	{
		return $this->gdoVar($this->gdoColumnOf($className)->name);
	}
	
	public function gdoValueOf($className)
	{
		return $this->gdoColumnOf($className)->getValue();
	}
	
	/**
	 * Get the GDOs AutoIncrement column, if any.
	 * @return GDT_AutoInc
	 */
	public function gdoAutoIncColumn() : GDT { return $this->gdoColumnOf(GDT_AutoInc::class); }
	
	/**
	 * Get the GDOs name identifier column, if any.
	 * @return GDT_Name
	 */
	public function gdoNameColumn() : ?GDT_Name
	{
		return $this->gdoColumnOf(GDT_Name::class);
	}
	
	/**
	 * Get the GDT column for a key.
	 * @param string $key
	 * @return GDT
	 */
	public function gdoColumn(string $key, bool $throw=true) : GDT
	{
		/** @var $gdt GDT **/
		if ($gdt = $this->gdoColumnsCache()[$key])
		{
			return $gdt->gdo($this);
		}
		elseif ($throw)
		{
			throw new GDO_Error('err_unknown_gdo_column', [$this->renderName(), html($key)]);
		}
		return null;
	}
	
// 	/**
// 	 * Get a copy of a GDT column.
// 	 * @param string $key
// 	 * @return GDT
// 	 */
// 	public function gdoColumnCopy($key)
// 	{
// 		/** @var $column GDT **/
// 		$column = clone $this->gdoColumnsCache()[$key];
// 		return $column->gdo($this);#->var($column->initial);
// 	}
	
	/**
	 * Get all GDT columns except those listed.
	 * @param string[] ...$except
	 * @return GDT[]
	 */
	public function gdoColumnsExcept(...$except) : array
	{
		$columns = [];
		foreach (array_keys($this->gdoColumnsCache()) as $key)
		{
			if (!in_array($key, $except, true))
			{
				$columns[$key] = $this->gdoColumn($key);
			}
		}
		return $columns;
	}
	
	##########
	### DB ###
	##########
	/**
	 * Create a new query for this GDO table.
	 * @return Query
	 */
	public function query() : Query
	{
		return new Query(self::table());
	}
	
	/**
	 * Find a row by AutoInc Id.
	 * @param string $id
	 * @return static
	 */
	public function find(string $id=null, bool $throw=true) : self
	{
		if ($id && ($gdo = $this->getById($id)))
		{
			return $gdo;
		}
		if ($throw)
		{
			self::notFoundException(html($id));
		}
	}
	
	public function findCached(...$ids) : ?self
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
	public function countWhere($condition='true') : int
	{
		return $this->select('COUNT(*)', false)->where($condition)->
		noOrder()->exec()->fetchValue();
	}
	
	/**
	 * Find a row by condition. Throws GDO::notFoundException.
	 * @param string $where
	 * @return self
	 */
	public function findWhere($condition) : ?self
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
	public function getWhere($condition) : ?self
	{
		return $this->select()->where($condition)->
		first()->exec()->fetchObject();
	}
	
	/**
	 * @param string $columns
	 * @return \GDO\DB\Query
	 */
	public function select(string $columns='*', bool $withHooks=true) : Query
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
		return !$this->isPersisted();
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
	public static function make(string $name=null) : GDT
	{
		throw new GDO_Error('err_gdo_no_gdt', ['make', self::gdoHumanNameS()]);
	}
	
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
	public static function getBlankData(array $initial = null)
	{
		$table = self::table();
		$gdoVars = [];
		foreach ($table->gdoColumnsCache() as $gdt)
		{
			# init gdt with initial var.
			if (isset($initial[$gdt->getName()]))
			{
				$var = $initial[$gdt->getName()];
				$gdt->var($var);
			}
			else
			{
				$gdt->var($gdt->getInitial());
			}
			
			# loop over blank data
			if ($data = $gdt->blankData())
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
		return self::entity(self::getBlankData($initial))->dirty()->setPersisted(false);
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
	public function getID() : ?string
	{
		if (isset($this->id))
		{
			return $this->id;
		}
		$id = '';
		foreach ($this->gdoPrimaryKeyColumnNames() as $name)
		{
			if ($name)
			{
				$id2 = $this->gdoVar($name);
				$id = $id ? "{$id}:{$id2}" : $id2;
			}
		}
		if ($id)
		{
			$this->id = $id;
		}
		return $id;
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
	public static function getBy(string $key, string $var) : ?self
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
	public static function findBy(string $key, string $var) : ?self
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
	 * Get a row by IDs.
	 * @param string ...$id
	 * @return self
	 */
	public static function getById(string...$id) : ?self
	{
		$table = self::table();
		if ( (!$table->cached()) || (!($object = $table->cache->findCached(...$id))) )
		{
			$i = 0;
			$query = $table->select();
			foreach ($table->gdoPrimaryKeyColumns() as $gdt)
			{
				$condition = $table->gdoTableName() . '.' . $gdt->identifier() .
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
	
	public function initCache() : void
	{
		$this->cache = new Cache($this);
	}
	
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
			foreach ($this->gdoPrimaryKeyColumns() as $gdt)
			{
				$query->where($gdt->identifier() . '=' . self::quoteS($id[$i++]));
			}
			$object = $query->uncached()->first()->exec()->fetchObject();
			return $object ? $table->cache->recache($object) : null;
		}
	}
	
	/**
	 * This function triggers a recache, also over IPC, if IPC is enabled.
	 */
	public function recache() : self
	{
		if ($this->cached())
		{
			self::table()->cache->recache($this);
		}
		return $this;
	}
	
	public function recacheMemcached() : void
	{
		if ($this->memCached())
		{
			$this->table()->cache->recache($this);
		}
	}
	
	public bool $recache; // @TODO move GDO->$recache to the Cache to reduce GDO by one field
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

	public function uncache() : void
	{
		if ($this->table()->cache)
		{
			$this->table()->cache->uncache($this);
		}
	}
	
	public function clearCache() : self
	{
		unset($this->id);
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
	public function &all(string $order=null, bool $json=false) : array
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
	public function &allCached(string $order=null, bool $json=false) : array
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
	
	##############
	###  Table ###
	##############
	/**
	 * Get the table GDO for a classname.
	 * 
	 * @param string $className
	 * @return self
	 */
	public static function tableFor(string $className, bool $throw=true) : ?self
	{
		$gdo = Database::tableS($className);
		if ($throw && (!$gdo))
		{
			throw new GDO_Error('err_table_gdo', [html($className)]);
		}
		return $gdo;
	}
	
	public function gdoTableName() : string { return $this->table()->cache->tableName; }
	public function gdoTableIdentifier() : string { return $this->gdoTableName(); }
	
	/**
	 * Check if this gdo row entity is the table GDO.
	 * This is done via the always generated cache object and should be efficient. The memory cost for the old private $isTable was horrible!
	 * @return bool
	 */
	public function gdoIsTable() : bool
	{
		return $this->table()->cache->isTable($this);
	}
	
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
		$this->beforeEvent('gdoBeforeCreate', $query);
	}
	
	private function beforeRead(Query $query) : void
	{
		$this->beforeEvent('gdoBeforeRead', $query);
	}
	
	private function beforeUpdate(Query $query) : void
	{
		$this->beforeEvent('gdoBeforeUpdate', $query);
	}
	
	private function beforeDelete(Query $query) : void
	{
		$this->beforeEvent('gdoBeforeDelete', $query);
	}
	
	private function beforeEvent(string $methodName, Query $query) : self
	{
		foreach ($this->gdoColumnsCache() as $gdt)
		{
			$gdt->gdo($this);
			call_user_func([$gdt, $methodName], $this, $query);
		}
		call_user_func([$this, $methodName], $this, $query);
		return $this;
	}
	
	private function afterCreate() : void
	{
		# Flags
		$this->dirty = false;
		$this->setPersisted();
		# Trigger event for GDT like AutoInc, EditedAt, CreatedBy, etc.
		$this->afterEvent('gdoAfterCreate');
	}
	
	private function afterRead() : void
	{
		$this->afterEvent('gdoAfterRead');
	}
	
	private function afterUpdate() : void
	{
		# Flags
		$this->dirty = false;
		$this->afterEvent('gdoAfterUpdate');
	}
	
	private function afterDelete() : void
	{
		# Flags
		$this->dirty = false;
		$this->persisted = false;
		$this->afterEvent('gdoAfterDelete');
	}
	
	private function afterEvent(string $methodName) : void
	{
		foreach ($this->gdoColumnsCache() as $gdt)
		{
			call_user_func([$gdt->gdo($this), $methodName], $this);
		}
		call_user_func([$this, $methodName], $this);
	}
	
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
	
}
