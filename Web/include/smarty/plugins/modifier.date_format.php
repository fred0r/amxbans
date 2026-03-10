<?php
/**
 * Smarty plugin
 *
 * @package    Smarty
 * @subpackage PluginsModifier
 */
/**
 * Convert strftime format to date format for PHP 8.1+ compatibility
 * @param string $format strftime format
 * @return string date format
 */
function strftime_to_date_format($format)
{
    static $conversions = array(
        '%a' => 'D',      // Abbreviated weekday name (Sun, Mon, etc.)
        '%A' => 'l',      // Full weekday name (Sunday, Monday, etc.)
        '%b' => 'M',      // Abbreviated month name (Jan, Feb, etc.)
        '%B' => 'F',      // Full month name (January, February, etc.)
        '%d' => 'd',      // Day of month (01-31)
        '%e' => 'j',      // Day of month without leading zeros (1-31)
        '%H' => 'H',      // Hour (00-23)
        '%I' => 'h',      // Hour (01-12)
        '%l' => 'g',      // Hour without leading zeros (1-12)
        '%m' => 'm',      // Month (01-12)
        '%M' => 'i',      // Minutes (00-59)
        '%p' => 'A',      // AM or PM
        '%S' => 's',      // Seconds (00-59)
        '%y' => 'y',      // Year (2 digits)
        '%Y' => 'Y',      // Year (4 digits)
        '%Z' => 'T',      // Timezone abbreviation
        '%z' => 'O',      // Timezone offset
        '%%' => '%',      // Literal percent sign
    );
    return strtr($format, $conversions);
}
/**
 * Smarty date_format modifier plugin
 * Type:     modifier
 * Name:     date_format
 * Purpose:  format datestamps via strftime
 * Input:
 *          - string: input date string
 *          - format: strftime format for output
 *          - default_date: default date if $string is empty
 *
 * @link   https://www.smarty.net/manual/en/language.modifier.date.format.php date_format (Smarty online manual)
 * @author Monte Ohrt <monte at ohrt dot com>
 *
 * @param string $string       input date string
 * @param string $format       strftime format for output
 * @param string $default_date default date if $string is empty
 * @param string $formatter    either 'strftime' or 'auto'
 *
 * @return string |void
 * @uses   smarty_make_timestamp()
 */
function smarty_modifier_date_format($string, $format = null, $default_date = '', $formatter = 'auto')
{
    if ($format === null) {
        $format = Smarty::$_DATE_FORMAT;
    }
    /**
     * require_once the {@link shared.make_timestamp.php} plugin
     */
    static $is_loaded = false;
    if (!$is_loaded) {
        if (!is_callable('smarty_make_timestamp')) {
            include_once SMARTY_PLUGINS_DIR . 'shared.make_timestamp.php';
        }
        $is_loaded = true;
    }
    if (!empty($string) && $string !== '0000-00-00' && $string !== '0000-00-00 00:00:00') {
        $timestamp = smarty_make_timestamp($string);
    } elseif (!empty($default_date)) {
        $timestamp = smarty_make_timestamp($default_date);
    } else {
        return;
    }
    if ($formatter === 'strftime' || ($formatter === 'auto' && strpos($format, '%') !== false)) {
        if (Smarty::$_IS_WINDOWS) {
            $_win_from = array(
                '%D',
                '%h',
                '%n',
                '%r',
                '%R',
                '%t',
                '%T'
            );
            $_win_to = array(
                '%m/%d/%y',
                '%b',
                "\n",
                '%I:%M:%S %p',
                '%H:%M',
                "\t",
                '%H:%M:%S'
            );
            if (strpos($format, '%e') !== false) {
                $_win_from[] = '%e';
                $_win_to[] = sprintf('%\' 2d', date('j', $timestamp));
            }
            if (strpos($format, '%l') !== false) {
                $_win_from[] = '%l';
                $_win_to[] = sprintf('%\' 2d', date('h', $timestamp));
            }
            $format = str_replace($_win_from, $_win_to, $format);
        }
        // Use date() instead of deprecated strftime() (removed in PHP 9.0)
        $date_format = strftime_to_date_format($format);
        return date($date_format, $timestamp);
    } else {
        return date($format, $timestamp);
    }
}
