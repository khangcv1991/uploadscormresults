<?php
/**
 * Settings definitions for the uploadscormresults tool
 *
 * @package    tool
 * @subpackage uploadscormresults
 * @copyright  &copy; 2012 Nine Lanterns Pty Ltd  {@link http://www.ninelanterns.com.au}
 * @author     ben.thomas
 * @version    1.0
 */

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $ADMIN->add('accounts', new admin_externalpage('uploadscormresults', get_string('pluginname', 'tool_uploadscormresults'), new moodle_url('/admin/tool/uploadscormresults/index.php'), 'tool/uploadscormresults:upload'));
}