<?php
namespace GDO\Core\Method;

use GDO\Core\Module_Core;
use GDO\DB\ArrayResult;
use GDO\Table\MethodTable;
use GDO\Util\FileUtil;
use GDO\Core\GDO_DirectoryIndex;
use GDO\Net\GDT_Url;

/**
 * Render a directory from the servers filesystem.
 * This can be disabled in Module_Core config.
 * 
 * @author gizmore
 *
 */
final class DirectoryIndex extends MethodTable
{
	public function isOrdered() { return false; }
	public function isFiltered() { return false; }
	public function isSearched() { return false; }
	public function isPaginated() { return false; }
	
	public function gdoParameters() : array
	{
		return [
			GDT_Url::make('url'),
		];
	}
	
	public function isAllowed()
	{
		return Module_Core::instance()->cfgDirectoryIndex();
	}
	
	public function execute()
	{
		if (!$this->isAllowed())
		{
			return $this->error('err_method_disabled', [$this->getModuleName(), $this->getMethodName()]);
		}
		return parent::execute();
	}
	
	public function gdoTable()
	{
		return GDO_DirectoryIndex::table();
	}
	
	public function getTableTitle()
	{
		$count = $this->table->countItems();
		return t('ft_dir_index', [html($this->getUrl()), $count]);
	}
	
	public function getUrl() : string
	{
		return $this->gdoParameterVar('url', true);
	}
	
	public function getResult()
	{
		$url = $this->getUrl();
		$data = [];
		$url = "./{$url}";
		$files = scandir($url);
		foreach ($files as $file)
		{
			if ($file === '.')
			{
				continue;
			}
			$path = $url . '/' . $file;
			$data[] = $this->entry($path, $file);
		}
		return new ArrayResult($data, $this->gdoTable());
	}
	
	private function entry($path, $filename)
	{
		if (is_dir($path))
		{
			return GDO_DirectoryIndex::blank([
				'file_icon' => 'folder',
				'file_name' => $filename,
				'file_type' => 'directory',
			]);
		}
		else
		{
			return GDO_DirectoryIndex::blank([
				'file_icon' => 'file',
				'file_name' => $filename,
				'file_type' => FileUtil::mimetype($path),
				'file_size' => filesize($path),
			]);
		}
	}
}
