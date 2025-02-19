<?php
namespace GDO\Table;

use GDO\Core\GDT;
use GDO\Core\WithInput;
use GDO\Core\WithName;

/**
 * Read various input sources into getFilterVars.
 * 
 * @author gizmore
 * @since 7.0.1
 */
final class GDT_Filter extends GDT
{
	use WithName;
	use WithInput;
	
	public function getDefaultName() : ?string
	{
		return 'f';
	}
	
	public function getFilterVars() : array
	{
		if (isset($_REQUEST[$this->name]))
		{
			return (array) $_REQUEST[$this->name];
		}
		return GDT::EMPTY_ARRAY;
	}
	
	public function plugVars() : array
	{
		return GDT::EMPTY_ARRAY;
	}
	
	public function getVar()
	{
		return isset($this->inputs) ? @$this->inputs[$this->name] : null;
	}
	
}
