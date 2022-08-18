<?php
namespace GDO\Tests\Test;

use GDO\Tests\TestCase;
use GDO\CLI\CLI;
use GDO\Core\Debug;
use GDO\Core\GDO;
use GDO\Util\Permutations;
use GDO\Core\Application;

/**
 * Try to save all GDO.
 * Once with blank. (crashtest) (REMOVED)
 * Once with plugged. (should be a success)
 * 
 * @author gizmore
 * @version 7.0.1
 */
final class AutomatedGDOSaveTest extends TestCase
{
	private int $gdoTested = 0;
	private int $gdoFailure = 0;
	private int $gdoSuccess = 0;
	private int $gdoAbstract = 0;
	private int $gdoNonTestable = 0;
	
	public function testGDOSave()
	{
		$this->message(CLI::bold("Starting the automated gdo save test!"));
		
		foreach (get_declared_classes() as $klass)
		{
			$parents = class_parents($klass);
			if (in_array('GDO\\Core\\GDO', $parents, true))
			{
				$this->gdoTested++;
				/** @var $gdo \GDO\Core\GDO **/
				$k = new \ReflectionClass($klass);
				if ($k->isAbstract())
				{
					$this->gdoAbstract++;
					continue;
				}
				$gdo = call_user_func([
					$klass,
					'make'
				], 'testfield');
				if ($gdo->gdoAbstract())
				{
					$this->gdoAbstract++;
					continue;
				}
				
				if ( (!$gdo->isTestable()) || ($gdo->gdoDTO()) )
				{
					$this->gdoNonTestable++;
					continue;
				}
				try
				{
					$this->saveTestGDO($gdo);
					$this->assert200("Test if {$gdo->gdoClassName()} can be saved.");
					$this->message("%4d.) %s: %s",
						$this->gdoTested,
						CLI::bold(CLI::green("SUCCESS")),
						get_class($gdo));
					$this->gdoSuccess++;
				}
				catch(\Throwable $t)
				{
					$this->error("%4d.) %s: %s - %s",
						$this->gdoTested,
						CLI::bold(CLI::red("FAILURE")),
						get_class($t),
						$t->getMessage());
					$this->error(Debug::backtraceException($t, false, $t->getMessage()));
					$this->gdoFailure++;
					Application::$INSTANCE->reset();
				}
			}
		}
	}
	
	###############
	### Private ###
	###############
	private function reportStatistics()
	{
		$this->message(CLI::bold("DONE!"));
		$this->message('Tested %d GDO', $this->gdoTested);
		$this->message('%s have succeeded. %s were abstract. %s. %s ',
			$this->gdoSuccess, $this->gdoAbstract, CLI::bold("{$this->gdoFailure} failed", CLI::bold("{$this->gdoNonTestable} were not testable.")));
	}

	private function saveTestGDOUnplugged(GDO $gdo)
	{
		$this->plugVariants = [];
		$new = $gdo->table()->cache->getNewDummy();
		if ($new->isValid())
		{
			$new->replace();
		}
	}
	
	private function saveTestGDO(GDO $gdo)
	{
		$this->plugVariants = [];
		foreach ($gdo->gdoColumnsCache() as $name => $gdt)
		{
			$gdt->inputs(); # clear input
			$this->addPlugVars($name, $gdt->plugVars());
		}
		
		$permutations = new Permutations($this->plugVariants);
		foreach ($permutations->generate() as $inputs)
		{
			$new = $gdo->table()->cache->getNewDummy();
			$new->setVars($inputs);
			if ($new->isValid())
			{
				$new->insert();
				$new->delete();
			}
			else
			{
				$this->error("%4d.) %s: %s - %s",
					$this->gdoTested,
					CLI::bold(CLI::red("WARNING")),
					$gdo->gdoClassName(),
					'has invalid plug vars');
			}
		}
	}

}
