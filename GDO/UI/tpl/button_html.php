<?php
namespace GDO\UI\tpl;
use GDO\UI\GDT_Button;
use GDO\Core\GDT;
/** @var $cell bool **/
/** @var $field GDT_Button **/
$label = $cell ? GDT::EMPTY_STRING : $field->renderLabelText();
if (!($href = $field->htmlGDOHREF()))
{
	$field->addClass('gdo-disabled');
}

?>
<div class="gdt-button"<?=$field->htmlAttributes()?>><a
<?=$field->htmlRelation()?>
<?=$href?>
<?=$field->htmlDisabled()?>><?=$field->htmlIcon()?><?=$label?></a></div>
