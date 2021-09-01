<?php
/**
 * Define default English language strings for tool
 *
 * @package    tool
 * @subpackage uploadscormresults
 * @copyright  &copy; 2012 Nine Lanterns Pty Ltd  {@link http://www.ninelanterns.com.au}
 * @author     ben.thomas
 * @version    1.0
 */

defined('MOODLE_INTERNAL') || die;

$string['pluginname'] = 'Upload SCORM results';
$string['description'] = '<strong>Upload a set of SCORM results and update completions.</strong><br />
Given a CSV of users and results/SCO track data, this tool will create all internal LMS records to track these results. Completion criteria will
be reviewed against the new results and any resultant completions will be recorded and backdated to match a date if specified.';
$string['csvloaderror'] = 'Error trying to load CSV: {$a}';
$string['csvempty'] = 'CSV empty or only has 1 line';
$string['csvcolempty'] = 'CSV needs at least 1 column';
$string['emptycolumn'] = 'Empty column header(s) found in CSV!';
$string['duplicatecolumn'] = 'Duplicate column header found in CSV!';
$string['usergroupheader'] = 'User mapping';
$string['usergroupdesc'] = 'Select all applicable CSV columns to uniquely identify the user against their LMS profile.';
$string['scogroupheader'] = 'SCO mapping';
$string['scogroupdesc'] = 'Select all applicable CSV columns to uniquely identify the SCO to record results against.<br /><br />
<em>Note: you can record the same result against multiple SCOes if you change the multiple SCOes behavior setting in the Process section below.</em>';
$string['trackgroupheader'] = 'SCO track records';
$string['trackgroupdesc'] = 'Select all applicable CSV columns that will be mapped to SCO track records';
$string['deftrackgroupheader'] = 'Default SCO track records';
$string['deftrackgroupdesc'] = 'Add any default records to include in each user/SCO group<br /><br />
<em>Note: CSV values will override these values.</em>';
$string['processheader'] = 'Processing options';
$string['previewcsv'] = 'Preview upload actions';
$string['processcsv'] = 'Process upload';
$string['addmore'] = 'Add more fields';
$string['backdatefield'] = 'Back date records to';
$string['backdatefield_help'] = 'Back date all records (SCO track, activity completion, course completion) to value in the specified CSV column';
$string['multisco'] = 'Action when multiple SCOes found';
$string['multisco_help'] = 'Specify what to do when multiple SCOes match the mapping criteria:
    <ul>
    <li><strong>Skip:</strong> ignore the entry and do not insert any track recordes</li>
    <li><strong>Duplicate:</strong> duplicate all track records across all SCOes that match.<br /><strong>Ensure that your SCO mapping is correct, it will not check to see if it matches multiple SCORM activities or even courses.</strong></li>
    </ul>';
$string['multiscoskip'] = 'Skip';
$string['multiscoduplicate'] = 'Duplicate';
$string['attemptfield'] = 'Attempt number';
$string['attemptfield_help'] = 'Determine what attempt number to use. Either select the default to create a new attempt, or select a CSV field that will contain the attempt number<br /><br />
    <em>Note: if an attempt already exists for this number, duplicate records may be created</em>';
$string['defaultattempt'] = 'Create a new attempt number';
$string['uploadscormresults:upload'] = 'Upload SCORM results';