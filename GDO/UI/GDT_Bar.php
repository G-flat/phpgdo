<?php
namespace GDO\UI;

use GDO\Core\GDT_Template;

class GDT_Bar extends GDT_Container
{
	public function renderCard() : string { return $this->renderHTML(); }
	public function renderCell() : string { return $this->renderHTML(); }
	public function renderForm() : string { return $this->renderHTML(); }
	public function renderHTML() : string
	{
		return GDT_Template::php('UI', 'bar_html.php', ['field' => $this]);
	}
	
}
