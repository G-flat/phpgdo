<?php
namespace GDO\UI;

/**
 * Add label fields to a GDT.
 * 
 * @author gizmore
 * @version 7.0.0
 * @since 5.0.1
 */
trait WithLabel
{
	##############
	### Label ####
	##############
	public ?string $labelRaw = null;
	public ?string $labelKey = null;
	public ?array $labelArgs = null;
	
	public function label(string $key, array $args = null) : self
	{
		$this->labelRaw = null;
		$this->labelKey = $key;
		$this->labelArgs = $args;
		return $this;
	}
	
	public function labelRaw(string $label) : self
	{
		$this->labelRaw = $label;
		$this->labelKey = null;
		$this->labelArgs = null;
		return $this;
	}
	
	public function hasLabel() : bool
	{
		return $this->labelKey || $this->labelRaw;
	}
	
	##############
	### Render ###
	##############
	public function displayLabel() : string
	{
		if ($this->label)
		{
			return t($this->label, $this->labelArgs);
		}
		if ($this->labelRaw)
		{
			return $this->labelRaw;
		}
	}
	
}
