<?php
namespace GDO\Util;

require 'php-filewalker/gizmore/Filewalker.php';

/**
 * Wrapper for my own lib :]
 * @author gizmore
 * @version 7.0.1
 */
final class Filewalker
{
	public static function traverse(string $path, string $pattern=null, callable $callback_file=null, callable $callback_dir=null, int $recursive=self::MAX_RECURSION, $args=null)
	{
		return \gizmore\Filewalker::traverse($path, $pattern, $callback_file, $callback_dir, $recursive, $args);
	}
}