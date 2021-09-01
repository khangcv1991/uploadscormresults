<?php
/**
 * Form definition for uploadscormresults tool
 *
 * @package    tool
 * @subpackage uploadscormresults
 * @copyright  &copy; 2012 Nine Lanterns Pty Ltd  {@link http://www.ninelanterns.com.au}
 * @author     ben.thomas
 * @version    1.0
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir.'/formslib.php'); // forms library
require_once($CFG->dirroot.'/admin/tool/uploaduser/user_form.php'); // borrow upload users form
require_once($CFG->dirroot.'/admin/tool/uploadscormresults/locallib.php');

/**
 * Define form snippet for the initial upload of CSV (use uploaduser form and add validation)
 */
class tool_uploadscormresults_csv_form extends admin_uploaduser_form1 {
    private $iid = null;
    private $cir = null;

    /**
     * Apply server-side validation rules.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // bail if other errors (as they are used for validating CSV)
        if(count($errors) > 0) {
            return $errors;
        }

        static $in_validation = false;

        // bail if recursed in (as get_file_contents checks validation)
        if($in_validation) {
            return $errors;
        }

        $in_validation = true;

        $iid = csv_import_reader::get_new_iid('uploadscormresults');
        $cir = new csv_import_reader($iid, 'uploadscormresults');

        $this->iid = $iid;

        $content = $this->get_file_content('userfile');

        $readcount = $cir->load_csv_content($content, $data['encoding'], $data['delimiter_name']);
        unset($content);

        if ($readcount === false) {
            $errors['userfile'] = get_string('csvloaderror', 'tool_uploadscormresults', $cir->get_error());
        } else if ($readcount < 1) {
            $errors['userfile'] = get_string('csvempty', 'tool_uploadscormresults');
        }

        $columns = $cir->get_columns();

        if(empty($columns) || count($columns) < 1) {
            $errors['userfile'] = get_string('csvcolempty', 'tool_uploadscormresults');
        }

        $in_validation = false;

        return $errors;
    }

    /**
     * Used to provide CSV iid to owner
     *
     * @return stdClass
     */
    function get_data() {
        $data = parent::get_data();

        if ($data !== null) {
            $data->iid = $this->iid;
        }

        return $data;
    }
}

/**
 * Define form snippet for the initial upload of CSV (use uploaduser form and add validation)
 */
class tool_uploadscormresults_mapping_form extends moodleform {
    private $iid;
    private $csvoptions;
    private $useroptions;
    private $scooptions;
    private $trackoptions;

    /**
     * The mform constructor.
     *
     * @param $csvoptions
     */
    function __construct($iid, $csvoptions, $useroptions, $scooptions, $trackoptions) {
        // initialise option lists with blanks
        $this->csvoptions = array('' => '');
        $this->useroptions = array('' => '');
        $this->scooptions = array('' => '');
        $this->trackoptions = array('' => '');

        // store info for populating form
        $this->iid = $iid;
        $this->csvoptions += $csvoptions;
        $this->useroptions += $useroptions;
        $this->scooptions += $scooptions;
        $this->trackoptions += $trackoptions;

        parent::__construct();
    }

    function definition() {
        $mform = $this->_form;

        // add hidden iid so we can maintain CSV reference
        $mform->addelement('hidden', 'iid', $this->iid);

        // user selection section
        $mform->addElement('header', 'usergroupheader', get_string('usergroupheader', 'tool_uploadscormresults'));

        // user selection description
        $mform->addElement('html', get_string('usergroupdesc', 'tool_uploadscormresults'));

        $grouparray = array();
        $grouparray[] =& $mform->createElement('select', 'userfield', '', $this->useroptions);
        $grouparray[] =& $mform->createElement('select', 'csvfield', '', $this->csvoptions);

        $group = $mform->createElement('group','usergroup', '', $grouparray);

        $this->repeat_elements(array($group), 2, array(), 'userepeats', 'useradds', 2, get_string('addmore', 'tool_uploadscormresults'), true);

        // sco selection section
        $mform->addElement('header', 'scogroupheader', get_string('scogroupheader', 'tool_uploadscormresults'));

        // sco selection description
        $mform->addElement('html', get_string('scogroupdesc', 'tool_uploadscormresults'));

        $grouparray = array();
        $grouparray[] =& $mform->createElement('select', 'scofield', '', $this->scooptions);
        $grouparray[] =& $mform->createElement('select', 'csvfield', '', $this->csvoptions);

        $group = $mform->createElement('group','scogroup', '', $grouparray);

        $this->repeat_elements(array($group), 2, array(), 'scorepeats', 'scoadds', 2, get_string('addmore', 'tool_uploadscormresults'), true);

        // track selection section
        $mform->addElement('header', 'trackgroupheader', get_string('trackgroupheader', 'tool_uploadscormresults'));

        // track selection description
        $mform->addElement('html', get_string('trackgroupdesc', 'tool_uploadscormresults'));

        $grouparray = array();
        $grouparray[] =& $mform->createElement('select', 'trackfield', '', $this->trackoptions);
        $grouparray[] =& $mform->createElement('select', 'csvfield', '', $this->csvoptions);

        $group = $mform->createElement('group','trackgroup', '', $grouparray);

        $this->repeat_elements(array($group), 4, array(), 'trackrepeats', 'trackadds', 2, get_string('addmore', 'tool_uploadscormresults'), true);

        // default track entry section
        $mform->addElement('header', 'deftrackgroupheader', get_string('deftrackgroupheader', 'tool_uploadscormresults'));

        // track selection description
        $mform->addElement('html', get_string('deftrackgroupdesc', 'tool_uploadscormresults'));

        $grouparray = array();
        $grouparray[] =& $mform->createElement('select', 'trackfield', '', $this->trackoptions);
        $grouparray[] =& $mform->createElement('text', 'trackvalue');

        $group = $mform->createElement('group','deftrackgroup', '', $grouparray);

        $this->repeat_elements(array($group), 4, array(), 'deftrackrepeats', 'deftrackadds', 2, get_string('addmore', 'tool_uploadscormresults'), true);

        // upload/processing settings and preview options
        $mform->addElement('header', 'processheader', get_string('processheader', 'tool_uploadscormresults'));

        // allow optional backdating
        $options = $this->csvoptions;
        $options[''] = get_string('defaultattempt', 'tool_uploadscormresults');
        $mform->addElement('select', 'attemptfield', get_string('attemptfield', 'tool_uploadscormresults'), $options);
        $mform->addHelpButton('attemptfield', 'attemptfield', 'tool_uploadscormresults');

        // allow optional backdating
        $mform->addElement('select', 'backdatefield', get_string('backdatefield', 'tool_uploadscormresults'), $this->csvoptions);
        $mform->addHelpButton('backdatefield', 'backdatefield', 'tool_uploadscormresults');

        // specify handling when multiple SCOs are found
        $choices = array(
            TOOL_UPLOADSCORMRESULTS_MULTISCO_SKIP => get_string('multiscoskip', 'tool_uploadscormresults'),
            TOOL_UPLOADSCORMRESULTS_MULTISCO_DUPLICATE => get_string('multiscoduplicate', 'tool_uploadscormresults'),
        );
        $mform->addElement('select', 'multisco', get_string('multisco', 'tool_uploadscormresults'), $choices);
        $mform->addHelpButton('multisco', 'multisco', 'tool_uploadscormresults');

        // need to include previewrows from uploaduser so we can change it again here if necessary
        $choices = array('10' => 10, '20' => 20, '100' => 100, '1000' => 1000, '100000' => 100000);
        $mform->addElement('select', 'previewrows', get_string('rowpreviewnum', 'tool_uploaduser'), $choices);
        $mform->setType('previewrows', PARAM_INT);

        // add buttons manually so we can have a preview button
        $buttonarray = array();
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('previewcsv', 'tool_uploadscormresults'));
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('processcsv', 'tool_uploadscormresults'));
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * Used to provide processing indicator and create mapping collections
     *
     * @return stdClass
     */
    function get_data() {
        if(!($data = parent::get_data())) {
            return $data;
        }

        $data->processnow = ($data->submitbutton == get_string('processcsv', 'tool_uploadscormresults'));

        $usermapping = array();
        foreach($data->usergroup as $mapping) {
            if(empty($mapping['userfield']) || empty($mapping['csvfield'])) {
                continue;
            }

            $usermapping[$mapping['userfield']] = $mapping['csvfield'];
        }
        $data->usermapping = $usermapping;

        $scomapping = array();
        foreach($data->scogroup as $mapping) {
            if(empty($mapping['scofield']) || empty($mapping['csvfield'])) {
                continue;
            }

            $scomapping[$mapping['scofield']] = $mapping['csvfield'];
        }
        $data->scomapping = $scomapping;


        $trackmapping = array();
        foreach($data->trackgroup as $mapping) {
            if(empty($mapping['trackfield']) || empty($mapping['csvfield'])) {
                continue;
            }

            $trackmapping[$mapping['trackfield']] = $mapping['csvfield'];
        }
        $data->trackmapping = $trackmapping;

        $deftrackmapping = array();
        foreach($data->deftrackgroup as $mapping) {
            $mapping['trackvalue'] = trim($mapping['trackvalue']);
            if(empty($mapping['trackfield']) || empty($mapping['trackvalue'])) {
                continue;
            }

            $deftrackmapping[$mapping['trackfield']] = $mapping['trackvalue'];
        }
        $data->deftrackmapping = $deftrackmapping;

        return $data;
    }

    /**
     * Provide alternate set_data format (matches used functionality)
     */
    function set_data($default_values) {
        $data = array();

        foreach($default_values['usermapping'] as $userfield => $csvfield) {
            $data['usergroup'][] = array('userfield' => $userfield, 'csvfield' => $csvfield);
        }

        foreach($default_values['scomapping'] as $scofield => $csvfield) {
            $data['scogroup'][] = array('scofield' => $scofield, 'csvfield' => $csvfield);
        }

        foreach($default_values['trackmapping'] as $trackfield => $csvfield) {
            $data['trackgroup'][] = array('trackfield' => $trackfield, 'csvfield' => $csvfield);
        }

        parent::set_data($data);
    }
    /**
     * Copied from moodleform_mod
     *
     * Method to add a repeating group of elements to a form.
     *
     * @param array $elementobjs Array of elements or groups of elements that are to be repeated
     * @param integer $repeats no of times to repeat elements initially
     * @param array $options Array of options to apply to elements. Array keys are element names.
     *                      This is an array of arrays. The second sets of keys are the option types
     *                      for the elements :
     *                          'default' - default value is value
     *                          'type' - PARAM_* constant is value
     *                          'helpbutton' - helpbutton params array is value
     *                          'disabledif' - last three moodleform::disabledIf()
     *                                           params are value as an array
     * @param string $repeathiddenname name for hidden element storing no of repeats in this form
     * @param string $addfieldsname name for button to add more fields
     * @param int $addfieldsno how many fields to add at a time
     * @param string $addstring name of button, {no} is replaced by no of blanks that will be added.
     * @param boolean $addbuttoninside if true, don't call closeHeaderBefore($addfieldsname). Default false.
     * @return int no of repeats of element in this page
     */
    function repeat_elements($elementobjs, $repeats, $options, $repeathiddenname,
            $addfieldsname, $addfieldsno=5, $addstring=null, $addbuttoninside=false){
        if ($addstring===null){
            $addstring = get_string('addfields', 'form', $addfieldsno);
        } else {
            $addstring = str_ireplace('{no}', $addfieldsno, $addstring);
        }
        $repeats = optional_param($repeathiddenname, $repeats, PARAM_INT);
        $addfields = optional_param($addfieldsname, '', PARAM_TEXT);
        if (!empty($addfields)){
            $repeats += $addfieldsno;
        }
        $mform =& $this->_form;
        $mform->registerNoSubmitButton($addfieldsname);
        $mform->addElement('hidden', $repeathiddenname, $repeats);
        $mform->setType($repeathiddenname, PARAM_INT);
        //value not to be overridden by submitted value
        $mform->setConstants(array($repeathiddenname=>$repeats));
        $namecloned = array();
        for ($i = 0; $i < $repeats; $i++) {
            foreach ($elementobjs as $elementobj){
                $elementclone = fullclone($elementobj);
                $this->repeat_elements_fix_clone($i, $elementclone, $namecloned);

                if ($elementclone instanceof HTML_QuickForm_group && !$elementclone->_appendName) {
                    foreach ($elementclone->getElements() as $el) {
                        $this->repeat_elements_fix_clone($i, $el, $namecloned);
                    }
                    $elementclone->setLabel(str_replace('{no}', $i + 1, $elementclone->getLabel()));
                }

                $mform->addElement($elementclone);
            }
        }
        for ($i=0; $i<$repeats; $i++) {
            foreach ($options as $elementname => $elementoptions){
                $pos=strpos($elementname, '[');
                if ($pos!==FALSE){
                    $realelementname = substr($elementname, 0, $pos+1)."[$i]";
                    $realelementname .= substr($elementname, $pos+1);
                }else {
                    $realelementname = $elementname."[$i]";
                }
                foreach ($elementoptions as  $option => $params){

                    switch ($option){
                        case 'default' :
                            $mform->setDefault($realelementname, $params);
                            break;
                        case 'helpbutton' :
                            $params = array_merge(array($realelementname), $params);
                            call_user_func_array(array(&$mform, 'addHelpButton'), $params);
                            break;
                        case 'disabledif' :
                            foreach ($namecloned as $num => $name){
                                if ($params[0] == $name){
                                    $params[0] = $params[0]."[$i]";
                                    break;
                                }
                            }
                            $params = array_merge(array($realelementname), $params);
                            call_user_func_array(array(&$mform, 'disabledIf'), $params);
                            break;
                        case 'rule' :
                            if (is_string($params)){
                                $params = array(null, $params, null, 'client');
                            }
                            $params = array_merge(array($realelementname), $params);
                            call_user_func_array(array(&$mform, 'addRule'), $params);
                            break;
                        case 'type' :
                            //Type should be set only once
                            if (!isset($mform->_types[$elementname])) {
                                $mform->setType($elementname, $params);
                            }
                            break;
                    }
                }
            }
        }
        $mform->addElement('submit', $addfieldsname, $addstring);

        if (!$addbuttoninside) {
            $mform->closeHeaderBefore($addfieldsname);
        }

        return $repeats;
    }
}