<?php
namespace GDO\UI;

/**
 * Should be extended to reflect a jQuery API on PHP objects.
 * 
 * @author gizmore
 * @version 6.11.4
 * @since 6.7.0
 */
trait WithPHPJQuery
{
	#######################
	### HTML Attributes ###
	#######################
	public array $htmlAttributes;
	
	public function attr($attribute, $value=null)
	{
		if (!$this->htmlAttributes)
		{
			$this->htmlAttributes = [];
		}
		if ($value === null)
		{
			return isset($this->htmlAttributes[$attribute]) ? 
			    $this->htmlAttributes[$attribute] : '';
		}
		$this->htmlAttributes[$attribute] = $value;
		return $this;
	}
	
	public function htmlAttributes()
	{
		$html = '';
		if ($this->htmlAttributes)
		{
			foreach ($this->htmlAttributes as $attribute => $value)
			{
				$html .= " $attribute=\"$value\"";
			}
		}
		return $html;
	}

	public function addClass($class)
	{
		# Old classes
		$classes = explode(" ", $this->attr('class'));
		if (!$classes)
		{
			$classes = [];
		}
		
		# Merge new classes
		$newclss = explode(" ", $class); # multiple possible
		foreach ($newclss as $class)
		{
		    if ($class = trim($class))
		    {
    			if (!in_array($class, $classes, true))
    			{
    				$classes[] = $class;
    			}
		    }
		}
		
		return $this->attr('class', implode(" ", $classes));
	}
	
	# CSS
	private array $css;
	public function css($attr, $value=null)
	{
		if (!$this->css) $this->css = [];
		if ($value === null) { return $this->css[$attr]; }
		$this->css[$attr] = $value;
		return $this->updateCSS();
	}
	
	private function updateCSS()
	{
		$rules = '';
		foreach ($this->css as $key => $value)
		{
			$rules .= "$key: $value; ";
		}
		return $this->attr('style', $rules);
	}

	/**
	 * @var callable
	 */
	public callable $click;
	
	/**
	 * Click handler.
	 * @param callable $callable
	 * @return self
	 */
	public function onclick(callable $callable) : self
	{
		$this->click = $callable;
		return $this;
	}
	
	public function click(...$args)
	{
		return call_user_func($this->click, ...$args);
	}
	
}
