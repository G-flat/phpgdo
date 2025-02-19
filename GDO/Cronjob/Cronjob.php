<?php
namespace GDO\Cronjob;

use GDO\Install\Installer;
use GDO\Core\ModuleLoader;
use GDO\DB\Database;
use GDO\Date\Time;
use GDO\User\GDO_User;
use GDO\Core\Application;

/**
 * Convinience cronjob launcher.
 * 
 * @author gizmore
 * @version 6.11.2
 * @since 6.5.0
 * @see MethodCronjob
 */
final class Cronjob
{
	private static $FORCE = false;
	
	/**
	 * Cronjobs main.
	 * Loop over all enabled modules to run cronjob.
	 */
	public static function run(bool $force=false) : void
	{
		self::$FORCE = $force;
	    GDO_User::setCurrent(GDO_User::system());
	    $loader = ModuleLoader::instance();
		$modules = $loader->loadModulesCache();
		$loader->initModules();
		
		if (module_enabled('Cronjob'))
		{
			foreach ($modules as $module)
			{
				if ($module->isEnabled())
				{
					Installer::loopMethods($module, [
						__CLASS__,
						'runCronjob'
					]);
				}
			}
			Module_Cronjob::instance()->setLastRun();
		}
		else
		{
			echo "Module_Cronjob is deactivated.\n";
		}
	}

	/**
	 * Path traversal entry point. Method is encoded in $entry
	 * @param string $entry
	 * @param string $path
	 * @param \GDO\Core\GDO_Module $module
	 */
	public static function runCronjob($entry, $path, $module)
	{
		$method = Installer::loopMethod($module, $path);
		if ($method instanceof MethodCronjob)
		{
		    if (self::shouldRun($method))
		    {
		        self::executeCronjob($method);
		    }
		}
	}
	
	private static function shouldRun(MethodCronjob $method)
	{
		if (self::$FORCE)
		{
			return true;
		}
		
		$module = Module_Cronjob::instance();
		$lastRun = $module->cfgLastRun();
		$dt = Time::parseDateTimeDB($lastRun);
		$minute = $dt->format('Y-m-d H:i');
		$dt = Time::parseDateDB($minute);
		$now = Application::$TIME;
		while ($dt <= $now)
		{
			if (self::shouldRunAt($method, $dt))
			{
				return true;
			}
			$dt += Time::ONE_MINUTE;
		}
		return false;
	}
	
	/**
	 * Check if in this minute the cron should have run.
	 * If not, the algo will compute for the next elapsed minute.
	 *  
	 * @param MethodCronjob $method
	 * @param int $timestamp
	 * @return boolean
	 */
	private static function shouldRunAt(MethodCronjob $method, $timestamp)
	{
		$at = $method->runAt();
		$at = preg_split("/[ \t]+/iD", $at);
		$att = date('i H j m N', $timestamp);
		$att = explode(' ', $att);
		$matches = 0;
		foreach ($at as $i => $a)
		{
			$aa = explode(',', $a);
			foreach ($aa as $aaa)
			{
				if ($aaa === '*')
				{
					$matches++;
					break;
				}
				if (strpos($aaa, '-') !== false)
				{
					$aaa = explode('-', $aaa);
					for ($j = $aaa[0]; $j <= $aaa[1]; $j++)
					{
						if ($att[$i] == $j)
						{
							$matches++;
							break;
						}
					}
				}
				elseif (strpos($aaa, '/') === 0)
				{
					$aaa = substr($aaa, 1);
					if (($att[$i] % $aaa) === 0)
					{
						$matches++;
						break;
					}
				}
				else
				{
					if ($att[$i] == $aaa)
					{
						$matches++;
						break;
					}
				}
			}
		}
		return $matches === 5;
	}

	public static function executeCronjob(MethodCronjob $method)
	{
		try
		{
			$db = Database::instance();
		    $job = GDO_Cronjob::blank([
		        'cron_method' => get_class($method),
		    ])->insert();
			$db->transactionBegin();
			$method->execute();
			$job->saveVars([
			    'cron_success' => '1',
			]);
			$db->transactionEnd();
		}
		catch (\Throwable $ex)
		{
		    if (isset($db))
		    {
		        $db->transactionRollback();
		        if (isset($job))
		        {
			        $job->saveVars([
			        	'cron_success' => '0',
			        ]);
		        }
		    }
			throw $ex;
		}
	}
	
}
