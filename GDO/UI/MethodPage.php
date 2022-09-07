<?php
namespace GDO\UI;

use GDO\Core\Method;
use GDO\Core\GDT_Template;

/**
 * Default method that simply loads a template.
 * Uses gdoParameters to populate template vars.
 * 
 * @author gizmore
 * @version 7.0.1
 * @since 6.4.0
 */
abstract class MethodPage extends Method
{
	public function execute()
	{
		return $this->pageTemplate();
	}
	
	protected function getTemplateName() : string
	{
		$name = strtolower($this->gdoShortName());
		return "page/{$name}.php";
	}
	
	protected function pageTemplate() : GDT_Template
	{
		return $this->templatePHP(
			$this->getTemplateName(),
			$this->getTemplateVars());
	}
	
	protected function getTemplateVars()
	{
		$tVars = [];
		foreach ($this->gdoParameters() as $param)
		{
			$tVars[$param->name] = $this->gdoParameterValue($param->name);
		}
		return $tVars;
	}

}
