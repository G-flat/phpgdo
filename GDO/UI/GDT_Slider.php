<?php
namespace GDO\UI;

use GDO\Core\GDT_Template;
use GDO\Core\GDT_Field;

/**
 * A numeric slider with min and max values. 
 * 
 * @author gizmore
 * @version 7.0.0
 * @since 6.0.0
 */
class GDT_Slider extends GDT_Field
{
	##############
	### Render ###
	##############
	public function renderForm() : string { return GDT_Template::php('UI', 'form/slider.php', ['field' => $this]); }

	###############
	### Options ###
	###############
	public $min;
	public function min($min) { $this->min = $min; return $this; }
	public $max;
	public function max($max) { $this->max = $max; return $this; }
	public $step = 1;
	public function step($step) { $this->step = $step; return $this; }

	################
	### Validate ###
	################
	public function getGDOData() : array { return [$this->name => $this->var]; }
	
	public function validate($value) : bool
	{
		if (parent::validate($value))
		{
			if (is_array($this->step))
			{
				if (!isset($this->step[$value]))
				{
					return $this->error('err_invalid_choice');
				}
			}
			elseif ($value !== null)
			{
				if ( ($value < $this->min) || ($value > $this->max) )
				{
					return $this->error('err_int_not_between', [$this->min, $this->max]);
				}
			}
			return true;
		}
	}
	
}
