<?phpnamespace GDO\Net\tpl\cell;use GDO\Net\GDT_Url;
/** @var $field GDT_Url **/$field->addClass('gdt-url');$url = $field->getValue();?><a <?=$field->htmlAttributes()?><?php if ($field->noFollow) : ?> rel="external nofollow"<?php endif; ?><?php if ($var = $field->getVar()) : ?> href="<?=html($var)?>"<?php endif; ?>><?= $field->hasTitle() ? $field->renderTitle() : html($url ? $url->getHost() : $field->renderLabel()) ?></a>