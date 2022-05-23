<?php
namespace GDO\Core;

/**
 * Adds error annotations to a GDT.
 * 
 * @author gizmore
 * @version 7.0.0
 * @since 6.1.0
 */
trait WithError
{
	public string $errorRaw;
	public string $errorKey;
	public ?array $errorArgs;
	
	/**
	 * Unlike the chain pattern, this returns false!
	 */
	public function error(string $key, array $args=null) : bool
	{
		unset($this->errorRaw);
		$this->errorKey = $key;
		$this->errorArgs = $args;
		return false;
	}
	
	public function errorRaw(string $message) : bool
	{
		$this->errorRaw = $message;
		unset($this->errorKey);
		unset($this->errorArgs);
		return false;
	}
	
	public function hasError() : bool
	{
		return isset($this->errorKey) || isset($this->errorRaw);
	}
	
	public function renderError() : string
	{
		if (isset($this->errorRaw))
		{
			return $this->errorRaw;
		}
		if (isset($this->errorKey))
		{
			return t($this->errorKey, $this->errorArgs);
		}
		return '';
	}
	
	/**
	 * Render error message as html form field error annotation.
	 */
	public function htmlError() : string
	{
		return $this->hasError() ?
			('<div class="gdo-form-error">' . $this->renderError() . '</div>') :
			'';
	}
	
}
