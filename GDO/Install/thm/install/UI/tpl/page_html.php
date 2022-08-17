<?php
use GDO\Perf\GDT_PerfBar;
use GDO\Language\Trans;
/** @var $page GDT_Page **/ 
?>
<!DOCTYPE html>
<html lang="<?=Trans::$ISO?>">
<head>
  <link rel="stylesheet" href="../install/gdo-install.css" />
</head>
<body>
  <header>
	<h1>GDOv7 Install Wizard (Rev.<?=Module_Core::GDO_REVISION?>)</h1>
	<?=GDT_Template::php('Install', 'crumb/progress.php')?>
  </header>
  <div class="gdo-body">
	<div class="gdo-main">
	  <?=$page->topResponse()->render()?>
	  <?=$page->html?>
	</div>
  </div>
  <footer>
	&copy;2022-2023 <a href="mailto: gizmore@wechall.net">gizmore@wechall.net</a> 
	<hr/>
  </footer>
</body>
</html>