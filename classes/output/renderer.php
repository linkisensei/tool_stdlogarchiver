<?php

defined('MOODLE_INTERNAL') || die();

use \tool_stdlogarchiver\output\renderables\search_results;
use \tool_stdlogarchiver\output\tables\search_results_table;
use \tool_stdlogarchiver\output\renderables\backup_list;
use \tool_stdlogarchiver\output\tables\backups_table;
use \tool_stdlogarchiver\output\tables\search_info;

class tool_stdlogarchiver_renderer extends plugin_renderer_base {

    public function render_search_results(search_results $renderable): void {
        global $OUTPUT, $PAGE;

        if (!$renderable->has_results()) {
            return;
        }

        if ($renderable->has_pending()) {
            echo $OUTPUT->notification(
                get_string('search:pending_notice', 'tool_stdlogarchiver'),
                \core\output\notification::NOTIFY_WARNING
            );
        }

        if ($renderable->get_skipped_csv_count() > 0) {
            echo $OUTPUT->notification(
                get_string('search:skipped_csv_notice', 'tool_stdlogarchiver',
                    $renderable->get_skipped_csv_count()),
                \core\output\notification::NOTIFY_INFO
            );
        }

        $table = new search_results_table('search-results', $PAGE->url);
        $table->display($renderable);

        $all_backups = $renderable->get_all_backups();
        if (!empty($all_backups)) {
            echo html_writer::tag('h5',
                get_string('search:search_info_title', 'tool_stdlogarchiver'),
                ['style' => 'margin-top:2rem;']
            );
            echo html_writer::tag('p',
                get_string('search:search_info_desc', 'tool_stdlogarchiver')
            );

            $info_table = new search_info('search-info', $PAGE->url);
            $info_table->display($renderable);
        }
    }

    public function render_backup_list(backup_list $renderable): void {
        global $OUTPUT, $PAGE;

        $searchurl = new moodle_url('/admin/tool/stdlogarchiver/search/index.php');
        echo $OUTPUT->single_button(
            $searchurl,
            get_string('searchbackups:title', 'tool_stdlogarchiver'),
            'get'
        );

        $table = new backups_table('logstore-backups', $PAGE->url);
        $table->display(iterator_to_array($renderable));

        echo $OUTPUT->paging_bar(
            $renderable->count_total(),
            $renderable->get_current_page(),
            $renderable->get_page_size(),
            $PAGE->url
        );
    }
}
