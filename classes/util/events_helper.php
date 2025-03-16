<?php namespace tool_stdlogarchiver\util;

use \core_component;
use \ReflectionClass;
use \core_plugin_manager;
use \xmldb_file;
use \xmldb_table;
use \moodle_exception;

class events_helper{

    /**
     * Returns a list of all instantiable event
     * classes found among Moodle components
     */
    public static function get_all_events() : array {
        ob_start();

        $valid_events = [];

        $events = core_component::get_component_classes_in_namespace(null, 'event');
        foreach ($events as $event => $_) {
            
            if(!is_a($event, \core\event\base::class, true)){
                continue;
            }

            $reflectionclass = new ReflectionClass($event);
            if($reflectionclass->isAbstract()){
                continue;
            }
        
            $event = "\\$event";
            $valid_events[$event] = $event;
        }
    
        ob_clean();

        return $valid_events;
    }

}