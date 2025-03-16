<?php 

$string['pluginname'] = "Arquivador de Logstore";
$string['settings:title'] = "Configurações do Arquivador de Logstore";
$string['settings:records_per_file'] = "Registros por arquivo";
$string['settings:backup_format'] = "Formato de backup";
$string['settings:log_lifetime'] = "Tempo de vida";
$string['settings:aws_region'] = "Região da AWS";
$string['settings:aws_key'] = "Chave";
$string['settings:aws_secret'] = "Segredo";
$string['settings:s3_bucket'] = "S3 Bucket";
$string['settings:s3_folder'] = "Diretório";
$string['settings:external_backup_service'] = "Serviço de backups externos";
$string['settings:delete_local_after_external_backup'] = "Manter somente backup externo";


$string['settings:records_per_file_desc'] = "Número máximo de registros de log por arquivo";
$string['settings:backup_format_desc'] = "";
$string['settings:log_lifetime_desc'] = "Tempo até um registro ser removido da tabela de armazenado no arquivo de backup.";
$string['settings:aws_s3_header'] = "Configurações da AWS";
$string['settings:aws_region_desc'] = "";
$string['settings:aws_key_desc'] = "";
$string['settings:aws_secret_desc'] = "";
$string['settings:s3_bucket_desc'] = "";
$string['settings:s3_folder_desc'] = "";
$string['settings:external_backup_service_desc'] = "";
$string['settings:delete_local_after_external_backup_desc'] = "Se habilitado, apaga o backup local após realizar o backup externo.";
$string['settings:settings_category'] = "Arquivador de Logstore";

$string['privacy:metadata:core_files'] = 'Armazena backups de registros do standard logstore na moodle data.';
$string['privacy:metadata:s3_bucket'] = 'Backups podem ser armazenados em um Bucket S3 na AWS';
$string['privacy:metadata:s3_bucket:userid'] = 'O userid faz parte dos registros de log armazenados no backup.';

$string['warning:logstore_standard_archive_task_active'] = '"{$a->configname}" está configurado para "{$a->configvalue}". Isso pode atrapalhar o funcionamento do plugin "Logstore Cleaner". <a href="{$a->url}">Altere as configurações aqui</a>';

$string['task:cleanup'] = "Tarefa de backup e limpeza de logs";
$string['task:external_backup'] = "Tarefa de sincronização externa de backups";

$string['external_service:s3'] = "Amazon S3";

$string['exception:cannot_download_local_file_exists'] = "Não foi possível baixar o arquivo externo, pois o arquivo local já existe";
$string['exception:starttime_is_required'] = "A data inicial é obrigatória";
$string['exception:endtime_is_required'] = "A data final é obrigatória";
$string['exception:search_interval_is_too_long'] = "O intervalo é muito longo para o filtro. Tente filter um único mês";
$string['exception:endtime_lesser_than_starttime'] = "A data final deve ser posterior a inicial";


$string['searchbackups:title'] = "Busca por registros de log";
$string['listbackups:title'] = "Backups do Logstore";

$string['filter:origin'] = "Origem";
$string['filter:userid'] = "ID do usuário";
$string['filter:relateduserid'] = "ID do usuário relacionado";
$string['filter:courseid'] = "ID do curso";

$string['table:id'] = "ID";
$string['table:starttime'] = "Data inicial";
$string['table:endtime'] = "Data final";
$string['table:fileformat'] = "Formato do backup";
$string['table:timecreated'] = "Data de criação";
$string['table:external_backup'] = "Backup externo";
$string['table:restored'] = "Restaurado";
$string['table:restoring'] = "Restauração em andamento";
$string['table:deleted'] = "Apagado";
$string['table:actions'] = "Ações";

$string['action:delete_local_backup'] = "Apagar backup local";
$string['action:restore_local_backup'] = "Restaurar backup";
$string['action:download_local_backup'] = "Download do backup";
$string['action:unrestore_local_backup'] = "Apagar logs restaurados";

$string['confirm:delete_local_backup'] = "Tem certeza de que deseja apagar este backup? Esta ação é irreversível!";
$string['confirm:restore_local_backup'] = "Tem certeza de que deseja restaurar este backup? Esta ação pode ser revertida.";
$string['confirm:unrestore_local_backup'] = "Deseja reverter a restauração deste backup? Os dados restaurados serão apagados.";

$string['exception:backup_not_found'] = "Backup não encontrado!";
$string['exception:backup_deleted'] = "Backup não encontrado!";
$string['delete_action_success'] = "Backup apagado!";
$string['exception:already_restoring'] = "O backup já está sendo restaurado.";
$string['exception:already_restored'] = "O backup já foi restaurado";
$string['restore_action_success'] = "O backup está sendo restaurado.";
$string['exception:not_yet_restored'] = "O backup ainda não foi restaurado.";
$string['unrestore_action_success'] = "A reversão de restauração de backup foi iniciada.";

$string['search:search_info_title'] = "Informações sobre os backups pesquisados";
$string['search:search_info_desc'] = "Para evitar timeouts, nem todos os backups encontrados são usados na busca. Segue abaixo, uma relação dos backups encontrados que foram ou não utilizados.";

$string['table:backupid'] = "Backup";
$string['table:firstid'] = "Primeiro ID";
$string['table:lastid'] = "Último ID";
$string['table:searched'] = "Pesquisado?";