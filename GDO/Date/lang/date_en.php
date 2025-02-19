<?php
namespace GDO\Date\lang;
return [
	'module_date' => 'Date and Time',
	'gdo_timezone' => 'Timezone',
    'ago' => '%s ago',
	'privacy_info_date_module' => 'Timezone and Last-Activity can give away information.',
	
    'err_min_date' => 'This date has to be after %s.',
    'err_max_date' => 'This date has to be before %s.',
    'err_invalid_date' => 'Invalid Time: %s does not match the format of %s. Please make sure to setup your language and timezone.',
    
    # Dateformats
    'df_db' => 'Y-m-d H:i:s.v', # do not change
    'df_local' => 'Y-m-d\TH:i', # do not change
    'df_parse' => 'm/d/Y H:i:s.u',
    'df_ms' => 'm/d/Y H:i:s.v',
    'df_long' => 'm/d/Y H:i:s',
    'df_short' => 'm/d/Y H:i',
    'df_minute' => 'Y-m-d H:i',
    'df_day' => 'm/d/Y',
    'df_sec' => 'm/d/Y H:i:s',
    'tu_s' => 's',
    'tu_m' => 'm',
    'tu_h' => 'h',
    'tu_d' => 'd',
    'tu_w' => 'w',
    'tu_y' => 'y',
    
    # Timezone
    'mt_date_timezone' => 'Set your timezone',
	'md_date_timezone' => 'Set your timezone on %s.',
    'msg_timezone_changed' => 'Your timezone has been changed to %s.',
    'cfg_tz_probe_js' => 'Probe timezone via Javascript?',
    'cfg_tz_sidebar_select' => 'Show timezone select in sidebar?',
	
	# Timezones
	'mt_timezones' => 'All Timezones',
	'md_timezones' => 'Get all timezones and offsets via ajax.',
    
    # Epoch
    'mt_date_epoch' => 'Print the unix timestamp',
    'msg_time_unix' => 'Unix timestamp: %s',
    'msg_time_java' => 'Java timestamp: %s',
    'msg_time_micro' => 'Microtimestamp: %s',
    
	# Duration
	'duration' => 'Duration',
	'err_min_duration' => 'The duration has to be at least %s seconds.',
	
	# Clock
	'cfg_clock_sidebar' => 'Show clock in sidebar?',
	
	# Activity Accuracy
	'activity_accuracy' => 'Activity Accuracy',
	'tt_activity_accuracy' => 'Control how exact your online activity is shown / last seen on...',

];
