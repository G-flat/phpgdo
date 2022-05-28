<?php
namespace GDO\Core;

/**
 * Add GDT parameters.
 * Override gdoParameters() in your methods.
 * 
 * @author gizmore
 * @version 7.0.0
 * @since 7.0.0
 * @see Method
 */
trait WithParameters
{
	#################
	### Protected ### - Override these
	#################
	/**
	 * Get method parameters.
	 * @return GDT[]
	 */
	public function gdoParameters() : array # @TODO: make gdoParameters() protected
	{
		return GDT::EMPTY_GDT_ARRAY;
	}
	
	##################
	### Parameters ###
	##################
// 	/**
// 	 * Compose all parameters. Not needed yet?
// 	 *
// 	 * @return GDT[]
// 	 */
// 	public function gdoComposeParameters() : array
// 	{
// 		return $this->gdoParameters();
// 	}
	
	public function gdoHasParameter(string $key) : bool
	{
		return isset($this->gdoParameterCache()[$key]);
	}
	
	/**
	 * Get a parameter by key.
	 * If key is an int, get positional parameter N.
	 */
	public function gdoParameter(string $key, bool $validate=true, bool $throw=true) : ?GDT
	{
		if ($gdt = $this->_gdoParameterB($key, $throw))
		{
			if ($validate)
			{
				if (!$gdt->validated())
				{
					if ($throw)
					{
						throw new GDO_Error('err_parameter', [html($key), $gdt->renderError()]);
					}
					return null;
				}
			}
		}
		return $gdt;
	}
	
	private function _gdoParameterB(string $key, bool $throw=true) : ?GDT
	{
		$cache = $this->gdoParameterCache();
		if (!($gdt = @$cache[$key]))
		{
			if (is_numeric($key))
			{
				$pos = -1;
				foreach ($cache as $_gdt)
				{
					if ($_gdt->isPositional())
					{
						$pos++;
						if ($pos == $key)
						{
							return $_gdt;
						}
					}
				}
				
			}
		}
		if (!$gdt)
		{
			if ($throw)
			{
				throw new GDO_Error('err_unknown_parameter', [html($key), $this->gdoHumanName()]);
			}
			return null;
		}
		return $gdt;
	}

	/**
	 * Get a parameter's GDT db var string.
	 */
	public function gdoParameterVar(string $key, bool $validate=false, bool $throw=true) : ?string
	{
		if ($gdt = $this->gdoParameter($key, $validate, $throw))
		{
			return $gdt->getVar();
		}
		return null;
	}
	
	public function gdoParameterValue(string $key, bool $validate=false, bool $throw=true)
	{
		if ($gdt = $this->gdoParameter($key, $validate, $throw))
		{
			return $gdt->getValue();
		}
		return null;
	}
	
	#############
	### Cache ###
	#############
	/**
	 * @var GDT[string]
	 */
	public array $parameterCache;
	
	/**
	 * @return GDT[string]
	 */
	public function &gdoParameterCache() : array
	{
		if (!isset($this->parameterCache))
		{
			$this->parameterCache = [];
			$this->addComposeParameters($this->gdoParameters());
		}
		return $this->parameterCache;
	}
	
	/**
	 * @param GDT[] $params
	 */
	protected function addComposeParameters(array $params) : void
	{
		foreach ($params as $gdt)
		{
			if ($name = $gdt->getName())
			{
				$this->parameterCache[$name] = $gdt->input($this->getInput($gdt->getName()));
			}
		}
	}
	
}
