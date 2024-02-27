<?php

require_once(__DIR__ . '/../../../../config.php');

require_login();


$url = new moodle_url('/admin/tool/stdlogarchiver/search/index.php', $_GET);
$url->remove_params('sesskey');

$backup_list_url = new moodle_url('/admin/tool/stdlogarchiver/index.php');

$context = context_system::instance();
require_capability('tool/stdlogarchiver:view', $context);

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_title(get_string('searchbackups:title', 'tool_stdlogarchiver'));
$PAGE->set_heading(get_string('searchbackups:title', 'tool_stdlogarchiver'));
$PAGE->set_pagelayout('report');

$PAGE->navbar->ignore_active();
$PAGE->navbar->add(get_string('listbackups:title', 'tool_stdlogarchiver'), $backup_list_url);
$PAGE->navbar->add(get_string('searchbackups:title', 'tool_stdlogarchiver'), $url);

// Filter form
$search_form = new tool_stdlogarchiver\output\renderables\forms\search_form();

$filters = $search_form->get_data();
if(empty($filters) || $search_form->is_cancelled()){
    $search_form->set_data([
        'starttime' => strtotime(date('Y-m-d', time() - 4 * WEEKSECS)),
        'endtime' => time(),
    ]);
}

// Output
$renderer = $PAGE->get_renderer('tool_stdlogarchiver');
echo $OUTPUT->header();

// Display the form.
$search_form->display();

// Seaching and displaying
try {
    $renderable = new tool_stdlogarchiver\output\renderables\search_results($filters);
    $renderer->render($renderable);
} catch (\Throwable $th) {
    \core\notification::error($th->getMessage());
}


echo $OUTPUT->footer();