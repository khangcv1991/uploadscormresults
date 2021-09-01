<?php
/**
 * Local library functions for uploadscormresults tool
 *
 * @package    tool
 * @subpackage uploadscormresults
 * @copyright  &copy; 2012 Nine Lanterns Pty Ltd  {@link http://www.ninelanterns.com.au}
 * @author     ben.thomas
 * @version    1.0
 */

defined('MOODLE_INTERNAL') || die;

define('TOOL_UPLOADSCORMRESULTS_MULTISCO_SKIP',         0);
define('TOOL_UPLOADSCORMRESULTS_MULTISCO_DUPLICATE',    1);

/**
 * Returns a cache of all table data recursively embedded as per mappedfields order
 * 
 * Note: will create join conditions but assumes fields are in correct order
 * already (i.e. won't resolve dependancy order), limited to certain conditions.
 */
function get_cache($fields, $mappedfields) {
    global $DB;
    
    $joined = array();
    $fromsql = "";
    $aliasedfields = array();
    $wheresql = array();
    
    foreach($fields as $fullfield) {
        list($table, $field) = explode('.', $fullfield, 2);
        
        $aliasedfields[] = "{$table}.{$field} AS {$table}_{$field}";
        
        // skip if already joined
        if(in_array($table, $joined, TRUE)) {
            continue;
        }
        
        $joined[] = $table;
        
        // special case profile field
        if(preg_match('/^user_profile_field_(.*)$/', $table, $matches)) {
            if(($rec = $DB->get_record('user_info_field', array('shortname' => $matches[1])))) {
                $fromsql .= " LEFT JOIN {user_info_data} AS {$table} ON ({$table}.fieldid = {$rec->id} AND {$table}.userid = user.id)";
            }
            
            continue;
        }
        
        switch($table) {
            case 'user':        // special case, should always be first
                $fromsql .= " FROM {user} AS user";
                break;
            case 'scorm_scoes':
                $fromsql .= " FROM {scorm_scoes} AS scorm_scoes";
                $wheresql[] = "scorm_scoes.scormtype = 'sco'";
                break;
            case 'scorm':
                $fromsql .= " INNER JOIN {scorm} AS scorm ON (scorm_scoes.scorm = scorm.id)";
                break;
            case 'course':      // special case, should always be first
                $fromsql .= " INNER JOIN {course} AS course ON (scorm.course = course.id)";
                break;
            case 'course_categories':
                $fromsql .= " INNER JOIN {course_categories} AS course_categories ON (course.category = course_categories.id)";
                break;
        }
    }
    
    $selectsql = "SELECT ".implode(', ', $aliasedfields);
    if(count($wheresql) > 0) {
        $wheresql = " WHERE ".implode(' AND ',$wheresql);
    } else {
        $wheresql = "";
    }
    
    if(!($recs = $DB->get_records_sql($selectsql.$fromsql.$wheresql, null, 0, 0))) {
        return false;
    }
    
    $aliasedfields = array();
    foreach($mappedfields as $field) {
        $aliasedfields[] = str_replace(".", "_", $field);
    }
    
    $cache = array();
    
    foreach($recs as $rec) {
        $key = array();
        
        foreach($aliasedfields as $field) {
            $key[$field] = $rec->{$field};
        }
        
        ksort($key);
        
        $key = serialize($key);
        
        if(!array_key_exists($key, $cache)) {
            $cache[$key] = array();
        }
        
        $cache[$key][] = $rec;
    }
    
    return $cache;
}
