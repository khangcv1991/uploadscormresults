<?php
/**
 * Upload a CSV and import SCORM results and run/backdate completions
 *
 * @package    tool
 * @subpackage uploadscormresults
 * @copyright  &copy; 2012 Nine Lanterns Pty Ltd  {@link http://www.ninelanterns.com.au}
 * @author     ben.thomas
 * @version    1.0
 */

/* TODO List
 *
 * Clean up strings
 * Allow defaults for user, course and skip CSV (so single entries can be done)
 * Allow overwriting results (e.g. clear out scorm suspend data)
 * Optimise/change cron completion calls
 * Major refactor
 */

define('NO_OUTPUT_BUFFERING', true);

require_once('../../../config.php');
require_once($CFG->dirroot.'/lib/adminlib.php');
require_once($CFG->dirroot.'/admin/tool/uploadscormresults/locallib.php');
require_once($CFG->dirroot.'/admin/tool/uploadscormresults/index_form.php');
require_once($CFG->dirroot.'/lib/tablelib.php');
// include the completion cron
require_once($CFG->dirroot.'/completion/cron.php');
// include scorm lib
require_once($CFG->dirroot.'/mod/scorm/lib.php');

global $CFG, $DB;

// bounce users who are not logged in
require_login();

// restrict access to this script
require_capability('tool/uploadscormresults:upload', get_system_context());

$baseurl = new moodle_url('/admin/tool/uploadscormresults/index.php');

$pagename = get_string('pluginname', 'tool_uploadscormresults');

// quick admin page setup
admin_externalpage_setup('uploadscormresults', null, null, $baseurl, array('pagelayout' => 'report'));

// render the theme header
echo $OUTPUT->header();
echo $OUTPUT->heading($pagename);

$iid         = optional_param('iid', '', PARAM_INT);
$previewrows = optional_param('previewrows', 10, PARAM_INT);

@set_time_limit(60*60); // 1 hour should be enough
raise_memory_limit(MEMORY_HUGE);

if(empty($iid)) {
    // still on upload CSV page
    $mform = new tool_uploadscormresults_csv_form();

    // process valid/submitted CSV
    if($mform->is_submitted() && $mform->is_validated() && ($data = $mform->get_data())) {
        $iid = $data->iid;
        $mform = null;
    }

    // mform still active (not submitted/validated or other error)
    if(!empty($mform)) {
        // output tool description
        echo $OUTPUT->box(get_string('description', 'tool_uploadscormresults'));

        // render the form
        $mform->display();

        // render the page footer
        echo $OUTPUT->footer();
        exit;
    }
}

// get all allowed user matching fields
$userfields = array ('user.id', 'user.idnumber', 'user.username', 'user.firstname', 'user.lastname', 'user.email');

// include profile fields as long as they are unique
if(($recs = $DB->get_records('user_info_field', array('forceunique' => 1)))) {
    foreach($recs as $rec) {
        $userfields[] = 'user_profile_field_' . $rec->shortname . '.data';
    }
}

// fields for finding SCOes
$scofields = array('scorm_scoes.id', 'scorm_scoes.identifier', 'scorm_scoes.title',
                    'scorm.id', 'scorm.name',
                    'course.id', 'course.idnumber', 'course.shortname', 'course.fullname',
                    'course_categories.id', 'course_categories.idnumber', 'course_categories.name'
);

// list all allowed scorm elements
$trackfields = array(
    'cmi.core.total_time',
    'x.start.time',
    'cmi.core.lesson_status',
    'cmi.core.score.raw',
);

// get all columns defined in CSV (marking duplicate and empty)
$cir = new csv_import_reader($iid, 'uploadscormresults');

$columns = $cir->get_columns();
$valid_cols = array();
$messages = array();

foreach($columns as $col) {
    $col = trim($col);
    if(empty($col)) {
        $messages['emptycolumn'] = get_string('emptycolumn', 'tool_uploadscormresults');
        continue;
    }

    if(in_array($col, $valid_cols, true)) {
        $messages['duplicatecolumn'] = get_string('duplicatecolumn', 'tool_uploadscormresults');
        continue;
    }

    $valid_cols[] = $col;
}

// generate csv, user, sco, sco elements option mappings for form
$csvoptions = array();
foreach($valid_cols as $col) {
    $csvoptions[$col] = $col;
}

$trackoptions = array();
foreach($trackfields as $field) {
    $trackoptions[$field] = $field;
}

$useroptions = array();
foreach($userfields as $field) {
    $useroptions[$field] = $field;
//    $useroptions[$field] = get_string($field, 'tool_uploadscormresults');
}

$scooptions = array();
foreach($scofields as $field) {
    $scooptions[$field] = $field;
//    $scooptions[$field] = get_string($field, 'tool_uploadscormresults');
}

$mform = new tool_uploadscormresults_mapping_form($iid, $csvoptions, $useroptions, $scooptions, $trackoptions);

$processnow = false;

// process valid/submitted CSV
if($mform->is_submitted() && $mform->is_validated() && ($data = $mform->get_data())) {
    // get mappings
    $usermapping = $data->usermapping;
    $scomapping = $data->scomapping;
    $trackmapping = $data->trackmapping;
    $deftrackmapping = $data->deftrackmapping;
    $attemptfield = $data->attemptfield;
    $backdatefield = $data->backdatefield;
    $multisco = $data->multisco;

    $processnow = $data->processnow;
} else {
    // setup default/best guess mappings
    $usermapping = array();

    foreach($userfields as $field) {
        if(!in_array($field, $valid_cols)) {
            continue;
        }

        $usermapping[$field] = $field;
    }

    $scomapping = array();

    foreach($scofields as $field) {
        if(!in_array($field, $valid_cols)) {
            continue;
        }

        $scomapping[$field] = $field;
    }

    $trackmapping = array();

    foreach($trackfields as $field) {
        if(!in_array($field, $valid_cols)) {
            continue;
        }

        $trackmapping[$field] = $field;
    }

    $deftrackmapping = array();
    $attemptfield = '';
    $backdatefield = '';
    $multisco = TOOL_UPLOADSCORMRESULTS_MULTISCO_SKIP;

    $data = compact('usermapping', 'scomapping', 'trackmapping');

    $mform->set_data($data);
}

$usercache = get_cache($userfields, array_keys($usermapping));
$scocache = get_cache($scofields, array_keys($scomapping));

$scormupdate = array();

// display any messages
foreach($messages as $message) {
    // output tool description
    echo $OUTPUT->box($message);
}

// set up the flexible table for displaying the records
$flextable = new flexible_table('tool_uploadscormresults');

// set the baseurl
$flextable->define_baseurl($PAGE->url);

$extracolumns = array(
    'user' => 'user',
    'sco' => 'sco',
    'action' => 'action'
);

$flextable->define_columns(array_merge(array('row'), $valid_cols, array_keys($extracolumns)));
$flextable->define_headers(array_merge(array('Row'), $valid_cols, array_values($extracolumns)));

// add some html attributes
$flextable->set_attribute('id', 'tool_uploadscormresults_actions');
$flextable->set_attribute('class', 'generaltable mdl-align');

// setup the table - now we can use it
$flextable->setup();

// init csv import helper
$cir->init();

$usermappingreverse = array();
foreach($usermapping as $key => $value) {
    $usermappingreverse[$value] = str_replace(".", "_", $key);
}
$scomappingreverse = array();
foreach($scomapping as $key => $value) {
    $scomappingreverse[$value] = str_replace(".", "_", $key);
}

$trackmappingreverse = array_flip($trackmapping);

$rowcount = 2;

// process all lines
while ($line = $cir->next()) {
    $userkey = array();
    $scokey = array();
    $tracks = $deftrackmapping;
    $row = array('row' => $rowcount++);

    // only do $previewrows if not processing
    if(!$processnow && $rowcount > $previewrows + 2) {
        break;
    }

    $backdateto = null;
    $attempt = null;
    $action = '';

    foreach($line as $i => $value) {
        $key = $columns[$i];

        // skip columns that have no heading
        if(empty($key)) {
            continue;
        }

        $row[$key] = $value;
        if(array_key_exists($key, $usermappingreverse)) {
            $userkey[$usermappingreverse[$key]] = $value;
        }

        if(array_key_exists($key, $scomappingreverse)) {
            $scokey[$scomappingreverse[$key]] = $value;
        }

        if(array_key_exists($key, $trackmappingreverse)) {
            $tracks[$trackmappingreverse[$key]] = $value;
        }

        if(!empty($backdatefield) && $backdatefield == $key) {
            if(!is_numeric($value)) {
                if(($dt = DateTime::createFromFormat('j/m/Y G:i', $value, timezone_open(usertimezone())))) {
                    $backdateto = $dt->getTimestamp();
                }
            }
        }

        if(!empty($attemptfield) && $attemptfield == $key) {
            $attempt = $value;
        }
    }

    ksort($userkey);
    ksort($scokey);
    $userkey = serialize($userkey);
    $scokey = serialize($scokey);

    $user = '';
    $sco = '';
    $useritem = null;
    $attemptno = array();

    if(array_key_exists($userkey, $usercache)) {
        if(count($usercache[$userkey]) > 1) {
            $user .= count($usercache[$userkey]) . ' matches';
            $action .= 'Skipped - multiple users<br />';
        } else {
            $useritem = $usercache[$userkey][0];
            $user .= $useritem->user_firstname . ' ' . $useritem->user_lastname . ' - ' .$useritem->user_id;
        }
    } else {
        $user .= 'No matches';
        $action .= 'Skipped - no users<br />';
    }

    if(array_key_exists($scokey, $scocache)) {
        if(count($scocache[$scokey]) > 1 && $multisco == TOOL_UPLOADSCORMRESULTS_MULTISCO_SKIP) {
            foreach($scocache[$scokey] as $scoitem) {
                $sco .= $scoitem->course_fullname . ' ' . $scoitem->course_id . ' ' . $scoitem->scorm_scoes_title . ' ' . $scoitem->scorm_scoes_id . '</ br>';
            }
            $action .= 'Skipped - multiple SCOes<br />';
        } else {
            foreach($scocache[$scokey] as $scoitem) {
                if(empty($attempt) && !empty($useritem)) {
                    $attempt = 1 + $DB->get_field_sql(
                        "SELECT MAX(attempt)
                            FROM {scorm_scoes_track}
                            WHERE
                            userid = :userid
                            AND scormid = :scormid
                            AND scoid = :scoid",
                            array('userid' => $useritem->user_id,
                                'scoid' => $scoitem->scorm_scoes_id,
                                'scormid' => $scoitem->scorm_id));
                }
                $attemptno[] = $attempt;
                $sco .= $scoitem->course_fullname . ' ' . $scoitem->course_id . ' ' . $scoitem->scorm_scoes_title . ' ' . $scoitem->scorm_scoes_id . '</ br>';

                if($processnow && !empty($useritem)) {
                    $track = new stdClass();
                    $track->userid = $useritem->user_id;
                    $track->scoid = $scoitem->scorm_scoes_id;
                    $track->scormid = $scoitem->scorm_id;
                    $track->attempt = $attempt;
                    $track->timemodified = time();
                    if(!empty($backdateto)) {
                        $track->timemodified = $backdateto;
                    }

                    foreach($tracks as $elem => $value) {
                        $value = trim($value);
                        // skip empty track values
                        if(empty($value)) {
                            continue;
                        }

                        $track->element = $elem;
                        $track->value = $value;

                        $DB->insert_record('scorm_scoes_track', $track);
                    }

                    if(!array_key_exists($track->scormid, $scormupdate)) {
                        $scormupdate[$track->scormid] = array();
                    }
                    $scormupdate[$track->scormid][$track->userid] = $backdateto;
                }
            }
        }
    } else {
        $sco .= 'No matches';
        $action .= 'Skipped - no SCOes<br />';
    }

    if(empty($action)) {
        if(!empty($backdateto)) {
            $action .= 'backdated to: '.userdate($backdateto).'<br />';
        }
        $action .= 'attempt no: '.implode(', ', $attemptno).'<br />';
        foreach($tracks as $track => $value) {
        $value = trim($value);
        // skip empty track values
        if(empty($value)) {
            continue;
        }

            $action .= $track . ' => ' . $value . '<br />';
        }
    }

    $row['user'] = $user;
    $row['sco'] = $sco;
    $row['action'] = '<div style="text-align: left;">'.$action.'</div>';

    // add the row to the table
    $flextable->add_data_keyed($row);
}

// finish the output
$flextable->finish_output();

$ccccache = array();
$mod_scorm = $DB->get_field('modules', 'id', array('name' => 'scorm'));
$scorms = $DB->get_records('scorm');

if($processnow) {
    foreach($scormupdate as $scorm => $users) {
        foreach($users as $user => $backdateto) {
            scorm_update_grades($scorms[$scorm], $user);
        }
    }
}

if($processnow) {
    $backdatefrom = time();

    echo '<pre>';

    completion_cron_mark_started();
    // mark activity criteria as complete
    completion_cron_criteria();

    foreach($scormupdate as $scorm => $users) {
        if(!array_key_exists($scorm, $ccccache)) {
            $cm_id = $DB->get_field('course_modules', 'id', array(
                'module' => $mod_scorm,
                'instance' => $scorm,
            ));
            $ccccache[$scorm] = $DB->get_field('course_completion_criteria', 'id', array(
                'moduleinstance' => $cm_id,
                'criteriatype' => COMPLETION_CRITERIA_TYPE_ACTIVITY,
            ));
        }

        foreach($users as $user => $backdateto) {
            if(!empty($backdateto)) {
                if(intval($DB->get_field('course_completion_crit_compl', 'timecompleted', array('criteriaid' => $ccccache[$scorm], 'userid' => $user))) >= $backdatefrom) {
                    $DB->set_field('course_completion_crit_compl', 'timecompleted', $backdateto, array('criteriaid' => $ccccache[$scorm], 'userid' => $user));
                }
            }
        }
    }

    // aggregate course completion criteria and mark users as complete
    completion_cron_completions();

    foreach($scormupdate as $scorm => $users) {
        foreach($users as $user => $backdateto) {
            if(!empty($backdateto)) {
                if(intval($DB->get_field('course_completions', 'timecompleted', array('course' => $scorms[$scorm]->course, 'userid' => $user))) >= $backdatefrom) {
                    $DB->set_field('course_completions', 'timecompleted', $backdateto, array('course' => $scorms[$scorm]->course, 'userid' => $user));
                }
            }
        }
    }

    echo '</pre>';

    $mform->display();
} else {
    // display mapping form
    $mform->display();
}

// render the page footer
echo $OUTPUT->footer();
