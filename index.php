<?php

require_once(__DIR__ . '/../../../config.php');

require_login();

$url = new \moodle_url('/admin/tool/stdlogarchiver/index.php', [
    'page' => optional_param('page', 1, PARAM_INT),
]);

// Processing backup actions
tool_stdlogarchiver\backup\actions_controller::route();

// Page setup
$context = context_system::instance();
require_capability('tool/stdlogarchiver:view', $context);

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_title(get_string('listbackups:title', 'tool_stdlogarchiver'));
$PAGE->set_heading(get_string('listbackups:title', 'tool_stdlogarchiver'));
$PAGE->set_pagelayout('standard');

$PAGE->navbar->ignore_active();
$PAGE->navbar->add(get_string('listbackups:title', 'tool_stdlogarchiver'), $url);


// Output
$renderer = $PAGE->get_renderer('tool_stdlogarchiver');
echo $OUTPUT->header();

// Seaching and displaying
$filters = [];
$page = optional_param('page', 0, PARAM_INT);
$sort = 'id';
$order = "ASC";

$renderable = new tool_stdlogarchiver\output\renderables\backup_list($filters, 'id', "ASC", $page);
$renderer->render($renderable);

echo $OUTPUT->footer();