<?php
namespace GDO\UI;

use GDO\Core\GDT_Template;
use GDO\Core\GDT;
use GDO\Core\WithFields;

/**
 * A tab panel.
 * 
 * @author gizmore
 * @version 7.0.1
 * @since 6.0.2
 */
final class GDT_Tab extends GDT
{
	use WithLabel;
	use WithFields;
	
	private static $TABNUM = 0;
	
	public function getDefaultName() : string
	{
		return 'tab' . (++self::$TABNUM);
	}

	##############
	### Render ###
	##############
	public function renderForm() : string
	{
		return GDT_Template::php('UI', 'tab_html.php', [
			'field' => $this, 'cell' => false]);
	}
	
	public function renderHTML() : string
	{
		return GDT_Template::php('UI', 'tab_html.php', [
			'field' => $this, 'cell' => true]);
	}

}
