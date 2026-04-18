<?php

require_once(__DIR__ . '/../../../config.php');

require_login();

$page = optional_param('page', 0, PARAM_INT);
$url  = new \moodle_url('/admin/tool/stdlogarchiver/index.php', ['page' => $page]);

\tool_stdlogarchiver\backup\actions_controller::route();

$context = context_system::instance();
require_capability('tool/stdlogarchiver:view', $context);

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_title(get_string('listbackups:title', 'tool_stdlogarchiver'));
$PAGE->set_heading(get_string('listbackups:title', 'tool_stdlogarchiver'));
$PAGE->set_pagelayout('standard');
$PAGE->navbar->ignore_active();
$PAGE->navbar->add(get_string('listbackups:title', 'tool_stdlogarchiver'), $url);

$renderer   = $PAGE->get_renderer('tool_stdlogarchiver');
$renderable = new \tool_stdlogarchiver\output\renderables\backup_list($page);

echo $OUTPUT->header();
$renderer->render($renderable);
echo $OUTPUT->footer();
