<?php

defined('MOODLE_INTERNAL') || die();

use \tool_stdlogarchiver\config;
use \tool_stdlogarchiver\models\backup;
use \tool_stdlogarchiver\output\renderables\search_results;
use \tool_stdlogarchiver\output\tables\search_results_table;
use \tool_stdlogarchiver\output\renderables\backup_list;
use \tool_stdlogarchiver\output\tables\backups_table;
use \tool_stdlogarchiver\output\tables\search_info;

class tool_stdlogarchiver_renderer extends plugin_renderer_base {

    public function render_search_results(search_results $renderable){
        global $OUTPUT, $PAGE;

        $table = new search_results_table('search-results', $PAGE->url);

        if($renderable->count_total_backups()){
            $table->display($renderable);

            echo html_writer::tag(
                'h5',
                get_string('search:search_info_title', 'tool_stdlogarchiver'),
                [
                    'style' => 'margin-top:2rem;',
                ]
            );
            echo html_writer::tag('span', get_string('search:search_info_desc', 'tool_stdlogarchiver'));

            $info_table = new search_info('search-info', $PAGE->url);
            $info_table->display($renderable);
        }
    }


    public function render_backup_list(backup_list $renderable){
        global $OUTPUT, $PAGE;

        $table = new backups_table('logstore-backups', $PAGE->url);
        $table->display($renderable);

        echo $OUTPUT->paging_bar(
            $renderable->count_total(),
            $renderable->get_current_page(),
            $renderable->get_page_size(),
            $PAGE->url
        );
    }
}