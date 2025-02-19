<?php
namespace GDO\Form;

use GDO\Core\GDT_Template;
use GDO\UI\GDT_Button;
use GDO\Core\WithInput;

/**
 * An input submit button is a button that can render submits inside forms.
 * 
 * @author gizmore
 * @version 7.0.1
 * @since 6.0.2
 */
class GDT_Submit extends GDT_Button
{
	use WithInput;
	use WithClickHandler;
	use WithFormAttributes;
	
	public string $icon = 'check';
	
	public function getDefaultName() : ?string
	{
		return 'submit';
	}

	public function renderForm() : string
	{
		return GDT_Template::php('Form', 'submit_form.php', [
			'field' => $this]);
	}
	
	/**
	 * The HTML value of a submit is the button label.
	 */
	public function htmlValue() : string
	{
		return ' value="'.$this->renderLabelText().'"';
	}
	
	public function plugVars() : array
	{
		$name = $this->getName();
		return [
			[$name => null],
			[$name => '1'],
		];
	}
	
}
