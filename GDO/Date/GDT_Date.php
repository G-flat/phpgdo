<?php
namespace GDO\Date;

use GDO\Core\GDT_Template;
use GDO\Core\GDT;

/**
 * A Date is like a Datetime, but a bit older, so we start with year selection.
 * An example is the release date of a book, or a birthdate.
 * 
 * @author gizmore
 * @version 7.0.1
 * @since 5.0.0
 * @see GDT_Time
 * @see GDT_DateTime
 * @see GDT_Timestamp
 * @see GDT_Duration
 */
class GDT_Date extends GDT_Timestamp
{
	public string $icon = 'calendar';
	public string $format = Time::FMT_DAY;
	public string $dateStartView = 'year';
	
	##########
	### DB ###
	##########
	public function gdoColumnDefine() : string
	{
		return "{$this->identifier()} DATE {$this->gdoNullDefine()}{$this->gdoInitialDefine()}";
	}

	###################
	### Var / Value ###
	###################
	public function toVar($value) : ?string
	{
	    return $value ? $value->format('Y-m-d') : null;
	}
	
	public function inputToVar($input) : ?string
	{
		if ($input === null)
		{
			return null;
		}

		# Not JS timestamp?
		if (!is_numeric($input))
		{
			$input = str_replace('T', ' ', $input);
			$input = str_replace('Z', '', $input);
			if (preg_match('#^\\d{4}-\\d{2}-\\d{2}#', $input))
			{
				$input = Time::parseDateTimeDB($input, Time::UTC);
			}
			else
			{
				$input = Time::parseDateTime($input, Time::UTC);
			}
		}
		else
		{
			# JS timestamp ms
			$input /= 1000.0;
			$input = Time::getDateTime($input);
		}
		
		return $input ? Time::displayDateTimeFormat($input, 'Y-m-d', '', Time::UTC) : null;
		
// 		$input = str_replace('T', ' ', $input);
// 		$input = str_replace('Z', '', $input);
// 		$time = Time::parseDate($input, Time::UTC);
// // 		$time = Time::parseDate($input);
// 		$input = Time::getDate($time, 'Y-m-d');
// 		return parent::inputToVar($input);
	}
	
	public function toValue($var = null)
	{
	    return empty($var) ? null : Time::parseDateTimeDB($var);
	}
	
	##############
	### Render ###
	##############
	public function renderHTML() : string
	{
		return Time::displayDate($this->getVar(), $this->format);
	}
	
	public function renderForm() : string
	{
		return GDT_Template::php('Date', 'form/date.php', ['field'=>$this]);
	}
	
	public function htmlValue() : string
	{
		if ($var = $this->getVar())
		{
			return sprintf(' value="%s"', html(substr($var, 0, 10)));
		}
		return GDT::EMPTY_STRING;
	}

}
