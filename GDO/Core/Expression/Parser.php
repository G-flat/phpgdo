<?php
namespace GDO\Core\Expression;

use GDO\Core\GDT_Expression;
use GDO\Core\Method;
use GDO\Core\GDO_ParseError;
use GDO\Util\Strings;

/**
 * Parse a CLI expression into an expression tree for execution.
 * A grad student probably would have pulled an lexer and AST stuff ;)
 * 
 * Syntax:
 * 
 * @example add 1;4 # => 5
 * @example concat a;$(wget https://google.de) # => a<!DOCTYPE....
 * @example add $(add 1;2);$(add 3;4) # => 10
 * 
 * @example mail giz;hi there(howdy;$(concat );   wget --abc ssh://)
 * 
 * @author gizmore
 * @version 7.0.0
 * @since 7.0.0
 * @see Method
 * @see GDT_Method
 * @see GDT_Expression
 */
final class Parser
{
	const CMD_PREAMBLE = '$';
	const ARG_SEPARATOR = ';';
	const ESCAPE_CHARACTER = '\\';
	
	private string $line;
	
	public function __construct(string $line)
	{
		$this->line = $line;
	}

	public function parse(string $line=null) : GDT_Expression
	{
		$this->line = $line ? $line : $this->line;
		$current = GDT_Expression::make();
		return $this->parseB($current, $this->line);
	}
	
	###############
	### Private ###
	###############
	private function parseB(GDT_Expression $current, string $line) : GDT_Expression
	{
		$i = 0;
		$l = $this->line;
		$len = mb_strlen($l);
		$method = $this->parseMethod($l, $i, $len);
		$current->method($method);
		$arg = '';
		for (; $i < $len;)
		{
			$c = $l[$i++];
			
			switch ($c)
			{
				case self::CMD_PREAMBLE:
					$line2 = $this->parseLine($l, $i, $len);
					$new = GDT_Expression::make()->parent($current);
					$this->addArgExpr($current, $new);
					$this->parseB($new, $line2);
					break;
				
				case self::ESCAPE_CHARACTER:
					$c2 = $l[$i++];
					$arg .= $c2;
					break;

				case self::ARG_SEPARATOR:
					if ($arg)
					{
						$this->addArg($current, $arg);
					}
					break;

				default:
					$arg .= $c;
					break;
			}
		}
		if ($arg)
		{
			$this->addArg($current, $arg);
		}
		return $current;
	}
	
	private function addArg(GDT_Expression $expression, string &$arg) : void
	{
		if (str_starts_with($arg, '--'))
		{
			$arg = substr($arg, 2);
			$key = Strings::substrTo($arg, '=', $arg);
			$input = Strings::substrFrom($arg, '=', '1');
		}
		else
		{
			$key = null;
			$input = $arg;
		}
		$arg = '';
		$expression->method->addInput($key, $input);
	}
	
	private function addArgExpr(GDT_Expression $expression, GDT_Expression $arg) : void
	{
		$expression->method->addInput(null, $arg->method);
	}

	private function parseMethod(string $line, int &$i, int $len) : Method
	{
		$parsed = '';
		$started = false;
		for (;$i < $len;)
		{
			$c = $line[$i++];
			if (ctype_space($c))
			{
				if ($started)
				{
					break;
				}
			}
			elseif (ctype_alnum($c))
			{
				$started = true;
				$parsed .= $c;
			}
			elseif ($c === '.')
			{
				$parsed .= '.';
			}
			else
			{
				break;
			}
		}
		return Method::getMethod($parsed);
	}
	
	/**
	 * Parse an additional line within paranstheses.
	 */
	private function parseLine(string $line, int &$i, int $len) : Method
	{
		# check if $(
		$c = $line[$i++];
		if ($c !== '(')
		{
			throw new GDO_ParseError('err_expression_preamble', [html($line)]);
		}
		
		$parsed = '';
		for (;$i < $len;)
		{
			$c = $line[$i++];
			
			switch ($c)
			{
				case self::ESCAPE_CHARACTER:
					$c2 = $line[$i++];
					$parsed .= $c2;
					break;
				
				case ')':
					break 2;
					
				default:
					$parsed .= $c;
					break;
			}
		}
		return $parsed;
	}
	
}
