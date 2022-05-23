<?php
namespace GDO\UI;

/**
 * Adds a title to a GDT.
 * This title is not rendered with a H tag.
 * 
 * @author gizmore
 * @version 7.0.0
 * @since 6.0.1
 * @see GDT_Headline
 */
trait WithTitle
{
	public string $titleKey;
	public ?array $titleArgs;
	public function title(string $key, array $args=null) : self
	{
		unset($this->titleRaw);
	    $this->titleKey = $key;
	    $this->titleArgs = $args;
	    return $this;
	}
	
	public string $titleRaw;
	public function titleRaw(string $title) : self
	{
	    $this->titleRaw = $title;
	    unset($this->titleKey);
	    unset($this->titleArgs);
	    return $this;
	}

	public bool $titleEscaped = true;
	public function titleEscaped(bool $escaped) : self
	{
	    $this->titleEscaped = $escaped;
	    return $this;
	}
	
	##############
	### Render ###
	##############
	public function hasTitle() : bool
	{
		return isset($this->titleKey) || isset($this->titleRaw);
	}
	
	public function renderTitle() : string
	{
		if (isset($this->titleKey))
		{
			return t($this->titleKey, $this->titleArgs);
		}
		elseif (isset($this->titleRaw))
		{
			return $this->titleRaw;
		}
		else
		{
			return '';
		}
	}
	
	public function noTitle() : self
	{
		unset($this->titleRaw);
		unset($this->titleKey);
		unset($this->titleArgs);
		return $this;
	}

}
