<?php

// ── Plugin ────────────────────────────────────────────────────────────────────
$string['pluginname']                   = 'Arquivador de Logstore';
$string['settings:settings_category']  = 'Arquivador de Logstore';
$string['settings:title']              = 'Configurações do Arquivador de Logstore';

// ── Capabilities ──────────────────────────────────────────────────────────────
$string['stdlogarchiver:view']    = 'Visualizar backups do logstore';
$string['stdlogarchiver:delete']  = 'Excluir backups do logstore';
$string['stdlogarchiver:restore'] = 'Restaurar backups do logstore';
$string['stdlogarchiver:config']  = 'Configurar o arquivador de logstore';

// ── Scheduled task names ──────────────────────────────────────────────────────
$string['task:archive_task_name']            = 'Arquivar registros do logstore em arquivos';
$string['task:external_migration_task_name'] = 'Enviar backups locais para armazenamento externo';
$string['task:cache_cleanup_task_name']      = 'Limpar arquivos de cache local expirados';
$string['task:backup_purge_task_name']       = 'Excluir permanentemente backups deletados';

// ── Settings: General ─────────────────────────────────────────────────────────
$string['settings:general_header']        = 'Geral';
$string['settings:backup_format']         = 'Formato de backup';
$string['settings:backup_format_desc']    = 'SQLite (.db) suporta pesquisa completa. CSV (.csv) é somente para arquivamento.';
$string['settings:max_records_per_file']      = 'Registros por arquivo';
$string['settings:max_records_per_file_desc'] = 'Número máximo de registros de log em um único arquivo de backup. Valores maiores reduzem a quantidade de arquivos, mas aumentam o uso de memória durante o arquivamento.';
$string['settings:log_lifetime']          = 'Período de retenção de logs';
$string['settings:log_lifetime_desc']     = 'Registros mais antigos que este valor são arquivados e removidos da tabela ativa do logstore.';

// ── Settings: Storage paths ───────────────────────────────────────────────────
$string['settings:storage_header']   = 'Caminhos de armazenamento';
$string['settings:backup_dir']       = 'Diretório de backups';
$string['settings:backup_dir_desc']  = 'Caminho absoluto para armazenar os arquivos de backup. Deixe em branco para usar o diretório padrão dentro do moodledata. Deve ter permissão de escrita para o servidor web.';
$string['settings:cache_dir']        = 'Diretório de cache';
$string['settings:cache_dir_desc']   = 'Caminho absoluto para cópias locais temporárias de arquivos de backup remotos. Deixe em branco para usar o diretório padrão dentro do moodledata.';
$string['settings:cache_ttl']        = 'TTL do cache';
$string['settings:cache_ttl_desc']   = 'Por quanto tempo uma cópia em cache de um arquivo remoto é mantida antes de ser excluída pela tarefa de limpeza de cache.';

// ── Settings: Retention & purge ───────────────────────────────────────────────
$string['settings:retention_header']          = 'Retenção e exclusão';
$string['settings:backup_retention_ttl']      = 'Período de retenção de backups';
$string['settings:backup_retention_ttl_desc'] = 'Backups mais antigos que este valor são automaticamente marcados como deletados e depois excluídos permanentemente pela tarefa de purga. Defina como 0 para desativar a exclusão automática.';

// ── Settings: External storage ────────────────────────────────────────────────
$string['settings:external_header']                  = 'Armazenamento externo';
$string['settings:external_backup_service']          = 'Serviço de armazenamento externo';
$string['settings:external_backup_service_desc']     = 'Enviar backups locais para um serviço externo de armazenamento após o arquivamento.';
$string['settings:external_migration_delay']         = 'Atraso para migração';
$string['settings:external_migration_delay_desc']    = 'Idade mínima que um backup deve ter antes de ser enviado para armazenamento externo. Defina como 0 para enviar imediatamente.';
$string['settings:delete_local_after_external']      = 'Excluir arquivo local após o envio';
$string['settings:delete_local_after_external_desc'] = 'Se habilitado, o arquivo local de backup é excluído após ser enviado com sucesso para o armazenamento externo.';

// ── Settings: AWS / S3 ────────────────────────────────────────────────────────
$string['settings:aws_s3_header']   = 'Configurações AWS / S3';
$string['settings:aws_region']      = 'Região da AWS';
$string['settings:aws_region_desc'] = 'Ex: us-east-1';
$string['settings:aws_key']         = 'Access Key ID';
$string['settings:aws_key_desc']    = '';
$string['settings:aws_secret']      = 'Secret Access Key';
$string['settings:aws_secret_desc'] = '';
$string['settings:s3_bucket']       = 'Bucket S3';
$string['settings:s3_bucket_desc']  = '';
$string['settings:s3_folder']       = 'Pasta no S3';
$string['settings:s3_folder_desc']  = 'Prefixo de chave (caminho de pasta) dentro do bucket.';

// ── External services ─────────────────────────────────────────────────────────
$string['external_service:s3'] = 'Amazon S3';

// ── Page titles ───────────────────────────────────────────────────────────────
$string['listbackups:title']   = 'Backups do Logstore';
$string['searchbackups:title'] = 'Buscar registros de log arquivados';

// ── Backup list table ─────────────────────────────────────────────────────────
$string['table:id']          = 'ID';
$string['table:starttime']   = 'Início do período';
$string['table:endtime']     = 'Fim do período';
$string['table:fileformat']  = 'Formato';
$string['table:timecreated'] = 'Criado em';
$string['table:searchable']  = 'Pesquisável';
$string['table:external']    = 'Externo';
$string['table:restored']    = 'Restaurado';
$string['table:restoring']   = 'Restaurando';
$string['table:deleted_at']  = 'Excluído em';
$string['table:actions']     = 'Ações';
$string['table:no_backups']  = 'Nenhum backup encontrado.';

// ── Search results table ──────────────────────────────────────────────────────
$string['table:backupid']          = 'Backup';
$string['table:firstid']           = 'Primeiro ID';
$string['table:lastid']            = 'Último ID';
$string['table:searched']          = 'Pesquisado?';
$string['table:no_search_results'] = 'Nenhum registro de log encontrado com os filtros informados.';

// ── Search filters ────────────────────────────────────────────────────────────
$string['filter:userid']        = 'ID do usuário';
$string['filter:relateduserid'] = 'ID do usuário relacionado';
$string['filter:courseid']      = 'ID do curso';
$string['filter:origin']        = 'Origem';

// ── Search info ───────────────────────────────────────────────────────────────
$string['search:search_info_title'] = 'Backups pesquisados';
$string['search:search_info_desc']  = 'A tabela abaixo mostra quais arquivos de backup foram consultados para o intervalo de tempo informado.';
$string['search:pending_notice']    = 'Alguns backups estão armazenados apenas em armazenamento externo e estão sendo baixados em segundo plano. Por favor, refaça a pesquisa em alguns minutos para incluir os resultados deles.';
$string['search:skipped_csv_notice'] = '{$a} backup(s) neste intervalo de tempo utilizam o formato CSV e não podem ser pesquisados. Apenas backups SQLite (.db) são pesquisáveis.';

// ── Actions ───────────────────────────────────────────────────────────────────
$string['action:delete_local_backup']    = 'Excluir backup';
$string['action:restore_local_backup']   = 'Restaurar backup';
$string['action:download_local_backup']  = 'Baixar backup';
$string['action:unrestore_local_backup'] = 'Desfazer restauração';

// ── Confirmations ─────────────────────────────────────────────────────────────
$string['confirm:delete_local_backup']   = 'Tem certeza que deseja excluir este backup? O arquivo será removido permanentemente.';
$string['confirm:restore_local_backup']  = 'Tem certeza que deseja restaurar este backup? Os registros serão reinseridos no logstore ativo.';
$string['confirm:unrestore_local_backup'] = 'Tem certeza que deseja desfazer a restauração? Os registros restaurados serão excluídos do logstore.';

// ── Action result messages ────────────────────────────────────────────────────
$string['delete_action_success']    = 'Backup excluído.';
$string['restore_action_success']   = 'Restauração agendada. O backup será restaurado em segundo plano.';
$string['unrestore_action_success'] = 'Desfazer restauração agendado. Os registros restaurados serão removidos em segundo plano.';

// ── Exceptions ────────────────────────────────────────────────────────────────
$string['exception:backup_not_found']                 = 'Backup não encontrado.';
$string['exception:backup_deleted']                   = 'Este backup foi excluído.';
$string['exception:already_restoring']                = 'Este backup já está sendo restaurado.';
$string['exception:already_restored']                 = 'Este backup já foi restaurado.';
$string['exception:not_yet_restored']                 = 'Este backup ainda não foi restaurado.';
$string['exception:starttime_is_required']            = 'A data de início é obrigatória.';
$string['exception:endtime_is_required']              = 'A data de fim é obrigatória.';
$string['exception:endtime_lesser_than_starttime']    = 'A data de fim deve ser posterior à data de início.';
$string['exception:search_interval_is_too_long']      = 'O intervalo de pesquisa é muito longo. Tente filtrar um período menor.';
$string['exception:cannot_download_local_file_exists'] = 'Não é possível baixar o arquivo externo: um arquivo local já existe.';

$string['backupfilenotavailable'] = 'O arquivo de backup não está disponível localmente nem no cache. Pode ser necessário baixá-lo do armazenamento externo primeiro.';
$string['noexternalbackup']       = 'Este backup não possui referência de armazenamento externo.';
$string['noexternalservice']      = 'Nenhum serviço de armazenamento externo está configurado ou habilitado.';
$string['externalbackupnotavailable'] = 'O arquivo de backup externo não está disponível ou não pôde ser verificado remotamente.';
$string['invalidbackupformat']    = 'Formato de arquivo de backup não suportado.';
$string['localfilenotfound']      = 'O arquivo de backup local não foi encontrado no disco.';

// ── Privacy / GDPR ────────────────────────────────────────────────────────────
$string['privacy:metadata:external_storage']        = 'Arquivos de backup podem ser armazenados em um serviço externo de armazenamento, como o Amazon S3.';
$string['privacy:metadata:external_storage:userid'] = 'Os registros de log nos arquivos de backup contêm o userid do usuário que gerou cada evento.';

// ── Warnings ──────────────────────────────────────────────────────────────────
$string['warning:logstore_standard_archive_task_active'] = '"{$a->configname}" está configurado como "{$a->configvalue}". Isso pode conflitar com o plugin Arquivador de Logstore. <a href="{$a->url}">Alterar configurações</a>';
