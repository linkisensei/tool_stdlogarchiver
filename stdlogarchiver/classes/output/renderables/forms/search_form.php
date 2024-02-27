<?php namespace tool_stdlogarchiver\output\renderables\forms;

require_once($CFG->libdir . '/formslib.php');

use \moodleform;
use \renderable;

use \tool_stdlogarchiver\config;
use \tool_stdlogarchiver\models\backup;

use \tool_stdlogarchiver\util\events_helper;

class search_form extends moodleform implements renderable{

    const MAX_SEARCHABLE_INTERVAL = 31 * DAYSECS;

    public function definition() {
        global $DB, $CFG;

        $this->_form->disable_form_change_checker();
        $mform = $this->_form;
        
        $first_backup = backup::get_first_backup();
        $first_backup_time = $first_backup ? date('Y', (int) $first_backup->get('starttime')) : 2014;

        $date_time_selector_options = [
            'startyear' => $first_backup_time,
            'stopyear'  => (int) date('Y'),
            'step'      => 5,
            'optional' => false,
        ];

        // From
        $mform->addElement(
            'date_time_selector',
            'starttime',
            get_string('from'),
            $date_time_selector_options,
            strtotime(date('Y-m-d', time() - 4 * WEEKSECS))
        );
        $mform->addRule('starttime', null, 'required');

        // To
        $mform->addElement(
            'date_time_selector',
            'endtime',
            get_string('to'),
            $date_time_selector_options,
            strtotime(date('Y-m-d', time()))
        );
        $mform->addRule('endtime', null, 'required');

        // Event name
        $mform->addElement(
            'autocomplete',
            'eventname',
            get_string('eventname'),
            events_helper::get_all_events(),
            [
                'noselectionstring' => get_string('all'), 
                'multiple' => false,
                'tags' => true
            ]
        );
        $mform->setType('eventname', PARAM_RAW);
        $mform->setDefault('eventname', '');

        // Component

        // User ID
        $mform->addElement(
            'text',
            'userid',
            get_string('filter:userid', 'tool_stdlogarchiver'),
            [
                'size' => '20'
            ]
        );
        $mform->setType('userid', PARAM_INT);

        // User ID
        $mform->addElement(
            'text',
            'relateduserid',
            get_string('filter:relateduserid', 'tool_stdlogarchiver'),
            [
                'size' => '20'
            ]
        );
        $mform->setType('relateduserid', PARAM_INT);

        // Course ID
        $mform->addElement(
            'text',
            'courseid',
            get_string('filter:courseid', 'tool_stdlogarchiver'),
            [
                'size' => '20'
            ]
        );
        $mform->setType('courseid', PARAM_INT);


        // Origin
        $mform->addElement(
            'select',
            'origin',
            get_string('filter:origin', 'tool_stdlogarchiver'),
            [
                null => get_string('all'),
                'ws' => 'Web Service',
                'web' => 'WEB',
                'cli' => 'CLI',
            ]
        );
        $mform->setDefault('origin', null);

        // Save Changes and Cancel buttons
        $this->add_action_buttons(true, get_string('search'));
    }

    function validation($data, $files) {
        $errors = [];

        if(intval($data['endtime']) < intval($data['starttime'])){
            $message = get_string('exception:endtime_lesser_than_starttime', 'tool_stdlogarchiver');
            $errors['endtime'] = $message;
        }

        if(intval($data['endtime']) - intval($data['starttime']) > self::MAX_SEARCHABLE_INTERVAL){
            $message = get_string('exception:search_interval_is_too_long', 'tool_stdlogarchiver');
            $errors['starttime'] = $message;
            $errors['endtime'] = $message;
        }

        return $errors;
    }
}