<?php
namespace GDO\Core;

/**
 * Add children fields to a GDT.
 * 
 * @author gizmore
 * @version 7.0.1
 * @since 6.0.1
 * @see GDT
 */
trait WithFields
{
	################
	### Instance ###
	################
	/**
	 * Call unnamed make and add fields.
	 */
	public static function makeWith(GDT...$gdt) : self
	{
		return self::make()->addFields(...$gdt);
	}
	
	##############
	### Fields ### More methods available here :)
	##############
	/**
	 * Real tree.
	 * @var GDT[string]
	 */
	public array $fields;
	
	/**
	 * Flattened fields.
	 * @var GDT[]
	 */
	public array $fieldsFlat;
	
	public function setFields(array $fields) : self
	{
		unset($this->fields);
		unset($this->fieldsFlat);
		$this->addFields(...array_values($fields));
		return $this;
	}
	
	public function addFields(GDT...$gdts) : self
	{
		foreach ($gdts as $gdt)
		{
			$this->addField($gdt);
		}
		return $this;
	}

	public function addField(GDT $gdt, GDT $after=null, bool $last=true) : self
	{
		return $this->addFieldB($gdt, $after, $last);		
	}
	
	public function addFieldFirst(GDT $gdt) : self
	{
		return $this->addFieldB($gdt, null, false);
	}
	
	protected function addFieldA(GDT $gdt, GDT $after=null, bool $last=true) : void
	{
		# Init
		if (!isset($this->fields))
		{
			$this->fields = [];
			$this->fieldsFlat = [];
		}

		# Check dup
// 		$name = $gdt->getName();
// 		if ($name && isset($this->fieldsFlat[$name]))
// 		{
// 			throw new GDO_Error('err_duplicate_field_name', [html($name)]);
// 		}
		
		if ($last)
		{
			
		}
		
		# Do the hard work
		$this->fields = $this->getFieldsSlicy($this->fields, $gdt, $last, $after);
		$this->fieldsFlat = $this->getFieldsSlicy($this->fieldsFlat, $gdt, $last, $after);
	}
	
	private function getFieldsSlicy(array $fields, GDT $field, bool $last, ?GDT $after)
	{
		# Build 3 slices depending on first, after, last.
		if ($last)
		{
			$begn = $fields;
			$midl = [$field];
			$aftr = GDT::EMPTY_ARRAY;
		}
		elseif ($after !== null)
		{
			$i = array_search($field, $fields, true);
			$begn = array_slice($fields, 0, $i);
			$midl = [$field];
			$aftr = array_slice($fields, $i, count($fields) - $i);
		}
		else # first
		{
			$begn = [$field];
			$midl = array_values($fields);
			$aftr = GDT::EMPTY_ARRAY;
		}
		
		# Build again
		$newfields = [];
		$all = array_merge($begn, $midl, $aftr);
		foreach ($all as $gdt)
		{
			if ($name = $gdt->getName())
			{
				$newfields[$name] = $gdt;
			}
			else
			{
				$newfields[] = $gdt;
			}
		}
		
		# Done :)
		return $newfields;
	}
	
	protected function addFieldB(GDT $gdt, GDT $after=null, bool $last=true) : self
	{
		$this->addFieldA($gdt, $after, $last);

		# Add to flatten
		if ($name = $gdt->getName())
		{
			$this->fieldsFlat[$name] = $gdt;
		}
		else
		{
			$this->fieldsFlat[] = $gdt;
		}
		
		# Add children in flatten only
		if ($gdt->hasFields())
		{
			$me = $this;
			$gdt->withFields(function(GDT $gdt) use ($me)
			{
				if ($name = $gdt->getName())
				{
					$me->fieldsFlat[$name] = $gdt;
				}
				else
				{
					$me->fieldsFlat[] = $gdt;
				}
			});
		}
		
		return $this;
	}
	
	public function removeFields() : self
	{
		unset($this->fields);
		unset($this->fieldsFlat);
		return $this;
	}
	
	public function removeFieldNamed(string $key) : self
	{
		$field = $this->getField($key);
		return $this->removeField($field);
	}
	
	public function removeField(GDT $field) : self
	{
		$i = array_search($field, $this->fields, true);
		unset($this->fields[$i]);
		$i = array_search($field, $this->fieldsFlat, true);
		unset($this->fieldsFlat[$i]);
		return $this;
	}
	
	public function hasFields(bool $ignoreHidden=false) : bool
	{
		if (!$ignoreHidden)
		{
			return count($this->getAllFields()) > 0;
		}
		else
		{
			foreach ($this->getAllFields() as $gdt)
			{
				if (!$gdt->isHidden())
				{
					return true;
				}
			}
			return false;
		}
	}
	
	/**
	 * @return GDT[]
	 */
	public function getFields() : array
	{
		return isset($this->fields) ? $this->fields : GDT::EMPTY_ARRAY;
	}
	
	public function getField(string $key, bool $throw=true) : ?GDT
	{
		if (isset($this->fieldsFlat[$key]))
		{
			return $this->fieldsFlat[$key];
		}
		elseif ($throw)
		{
			throw new GDO_Error('err_unknown_field', [html($key)]);
		}
		else
		{
			return null;
		}
	}
	
	/**
	 * Get all fields in a flattened array.
	 * @return GDT[]
	 */
	public function getAllFields() : array
	{
		return isset($this->fieldsFlat) ? $this->fieldsFlat : GDT::EMPTY_ARRAY;
	}
	
	###########################
	### Iterate recursively ###
	###########################
	/**
	 * Iterate recusively over the fields with a callback.
	 * If the result is truthy, break the loop early and return the result.
	 */
	public function withFields($callback, bool $returnEarly=false)
	{
		if (isset($this->fields))
		{
			foreach ($this->fields as $gdt)
			{
				if ($result = $callback($gdt))
				{
					if ($returnEarly)
					{
						return $result;
					}
				}
// 				if ($gdt->hasFields())
// 				{
// 					return $gdt->withFields($callback);
// 				}
			}
		}
	}
	
	/**
	 * Iterate recusively over the fields until we find the one with the key/name/pos.
	 * Then call the callback with it and return the result.
	 * Supports both, named and positional fields.
	 * 
	 * @param string|int $key
	 * @param callable $callback
	 */
	public function withField($key, $callback)
	{
		if (isset($this->fields))
		{
			foreach ($this->fields as $k => $gdt)
			{
				if ($k == $key)
				{
					return $callback($gdt);
				}
				if ($gdt->hasFields())
				{
					$gdt->withFields($callback);
				}
			}
		}
	}
	
	##############
	### Render ### - 
	##############
	public function render()
	{
		return $this->renderGDT();
	}
	
	/**
	 * WithFields, we simply iterate over them and render current mode.
	 */
	public function renderFields(int $renderMode) : string
	{
		return $this->renderFieldsB($renderMode);
	}
	
	protected function renderFieldsB(int $renderMode) : string
	{
		$app = Application::$INSTANCE;
		$rendered = '';
		if (isset($this->fields))
		{
			$old = $app->mode;
			$app->mode($renderMode);
			foreach ($this->fields as $gdt)
			{
				$rendered .= $gdt->render();
			}
			$app->mode($old);
		}
		return $rendered;
	}
	
	public function renderChoice() : string { return $this->renderFields(GDT::RENDER_CHOICE); }
	public function renderList() : string { return $this->renderFields(GDT::RENDER_LIST); }
	public function renderHTML() : string { return $this->renderFields(GDT::RENDER_HTML); }
	public function renderCell() : string { return $this->renderFields(GDT::RENDER_CELL); }
	public function renderForm() : string { return $this->renderFields(GDT::RENDER_FORM); }
	public function renderCLI() : string { return $this->renderFields(GDT::RENDER_CLI); }
	public function renderCard() : string { return $this->renderFields(GDT::RENDER_CARD); }
	public function renderPDF() : string { return $this->renderFields(GDT::RENDER_PDF); }
	public function renderXML() : string { return $this->renderFields(GDT::RENDER_XML); }
	public function renderBinary() : string { return $this->renderFields(GDT::RENDER_BINARY); }

	public function renderJSON()
	{
		$json = [];
		$this->withFields(function(GDT $gdt) use (&$json) {
			if ($gdt->hasName())
			{
				$json[$gdt->getName()] = $gdt->renderJSON();
			}
			else
			{
				$json[] = $gdt->renderJSON();
			}
		});
		return $json;
	}
	
}
