<?php
namespace GDO\Install\Test;

use GDO\Tests\TestCase;
use GDO\Core\ModuleLoader;
use GDO\Install\Installer;
use GDO\DB\Database;
use function PHPUnit\Framework\assertTrue;
use GDO\Core\Method\ClearCache;
use function PHPUnit\Framework\assertGreaterThanOrEqual;

/**
 * Install all available modules.
 * Wipe everything. reset cache.
 * The real testing begins afterwards.
 * 
 * @author gizmore
 * @version 7.0.1
 * @since 6.10.0
 */
final class InstallAllModulesTest extends TestCase
{
	public function testInstallAllModules()
	{
		$loader = ModuleLoader::instance();
		$loader->loadModules(false, true, true);
		$modules = $loader->getInstallableModules();
		Installer::installModules($modules);
		$this->assertOK('Check if all modules can be installed.');
		$installed = $loader->getEnabledModules();
		assertGreaterThanOrEqual(5, count($installed), 'Test if installer utility works.');
	}
	
	public function testWipeAllModules()
	{
		echo "Clearing all caches again, deeply!\n";
		ClearCache::make()->clearCache();
		
		echo "Wiping database!\n";
		
		$db = Database::instance();
		$result = $db->dropDatabase(GDO_DB_NAME);
		assertTrue(!!$result, 'Test if db can be dropped');
	
		$result = $db->createDatabase(GDO_DB_NAME);
		assertTrue(!!$result, 'Test if db can be re-created');
		
		$result = $db->useDatabase(GDO_DB_NAME);
		assertTrue(!!$result, 'Test if db can be re-used');
	}
	
}
