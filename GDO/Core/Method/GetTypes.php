<?php
namespace GDO\Core\Method;

use GDO\Core\ModuleLoader;
use GDO\Core\GDO;
use GDO\Core\MethodAjax;
use GDO\Core\GDT_JSON;
use GDO\Core\WithFileCache;

/**
 * Get all types used in all tables.
 * Get the type class hierarchy.
 * Is file cached.
 * 
 * @author gizmore
 * @version 7.0.1
 * @since 6.7.0
 */
final class GetTypes extends MethodAjax
{
	use WithFileCache;
	
	public function execute()
	{
		$tables = [];
		# Add non abstract module tables
		foreach (ModuleLoader::instance()->getEnabledModules() as $module)
		{
			if ($classes = $module->getClasses())
			{
				foreach ($classes as $class)
				{
					if (is_subclass_of($class, 'GDO\\Core\\GDO'))
					{
						if ($table = GDO::tableFor($class))
						{
							if (!$table->gdoAbstract())
							{
								$tables[] = $table;
							}
						}
					}
				}
			}
		}
		
		# Sum table fields
		$fields = [];
		foreach ($tables as $table)
		{
			$fields[$table->gdoClassName()] = [];
			foreach ($table->gdoColumnsCache() as $name => $gdt)
			{
				if ($gdt->isSerializable())
				{
					$fields[$table->gdoClassName()][$name] = array(
						'type' => $gdt->gdoClassName(),
						'options' => $gdt->configJSON(),
					);
				}
			}
		}
		
		# Build type hiararchy (GDTs that are no GDO)
		$types = [];
		foreach (get_declared_classes() as $class)
		{
			if (is_subclass_of($class, "GDO\\Core\\GDT"))
			{
				if (!is_subclass_of($class, "GDO\\Core\\GDO"))
				{
					$types[$class] = array_values(class_parents($class));
				}
			}
		}
		
		$json = [
			'fields' => $fields,
			'types' => $types,
		];

		return GDT_JSON::make()->value($json);
	}
	
}
