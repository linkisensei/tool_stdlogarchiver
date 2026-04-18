# tool_stdlogarchiver — Arquitetura e Funcionamento

> **Versão:** 2024120101 | **Moodle:** 4.2+ | **PHP:** 8.1+
> **Tipo:** Admin Tool | **Maturidade:** BETA

---

## Índice

1. [Visão Geral](#1-visão-geral)
2. [Banco de Dados](#2-banco-de-dados)
3. [Configurações](#3-configurações)
4. [Controle de Acesso](#4-controle-de-acesso)
5. [Formatos de Arquivo](#5-formatos-de-arquivo)
6. [Modelo de Dados — `backup`](#6-modelo-de-dados--backup)
7. [Processo: Arquivamento](#7-processo-arquivamento)
8. [Processo: Migração Externa (S3)](#8-processo-migração-externa-s3)
9. [Processo: Limpeza de Cache](#9-processo-limpeza-de-cache)
10. [Processo: Purga Permanente](#10-processo-purga-permanente)
11. [Processo: Busca em Logs Arquivados](#11-processo-busca-em-logs-arquivados)
12. [Processo: Download de Cache Assíncrono](#12-processo-download-de-cache-assíncrono)
13. [Processo: Restauração de Backup](#13-processo-restauração-de-backup)
14. [Processo: Desfazer Restauração](#14-processo-desfazer-restauração)
15. [Processo: Download de Arquivo](#15-processo-download-de-arquivo)
16. [Processo: Exclusão pelo Admin](#16-processo-exclusão-pelo-admin)
17. [Interface de Usuário](#17-interface-de-usuário)
18. [Camada de Armazenamento Externo](#18-camada-de-armazenamento-externo)
19. [Tarefas Agendadas — Resumo](#19-tarefas-agendadas--resumo)
20. [Privacidade e GDPR](#20-privacidade-e-gdpr)
21. [Dependências Externas](#21-dependências-externas)

---

## 1. Visão Geral

O `tool_stdlogarchiver` arquiva automaticamente registros antigos da tabela `logstore_standard_log` do Moodle em arquivos SQLite ou CSV. Os arquivos podem ser mantidos localmente no servidor e/ou sincronizados com um serviço de object storage externo (atualmente: Amazon S3).

**Problema que resolve:** A tabela `logstore_standard_log` cresce indefinidamente, degradando a performance de consultas e backups do banco de dados. O plugin move registros antigos para arquivos auto-indexados, mantendo a capacidade de busca e restauração.

**Capacidades principais:**

| Capacidade | Descrição |
|-----------|-----------|
| Arquivamento agendado | Roda diariamente, move registros antigos para arquivo |
| Busca | Consulta diretamente nos arquivos SQLite via SQL |
| Restauração | Reinserção dos registros originais no banco de dados |
| Armazenamento externo | Upload para S3; download sob demanda para busca |
| Purga automática | Exclui arquivos físicos após TTL de retenção |
| Download admin | Permite baixar qualquer arquivo de backup pelo navegador |

---

## 2. Banco de Dados

### Tabela: `tool_stdlogarchiver_backups`

Uma única tabela registra todos os metadados de backup. Não há tabelas auxiliares.

| Coluna | Tipo | Nulo | Descrição |
|--------|------|------|-----------|
| `id` | INT PK | Não | Auto-incremento |
| `firstid` | INT | Não | ID do primeiro registro de log neste backup |
| `lastid` | INT | Não | ID do último registro de log neste backup |
| `starttime` | INT | Não | `timecreated` do primeiro registro (Unix timestamp) |
| `endtime` | INT | Não | `timecreated` do último registro (Unix timestamp) |
| `fileformat` | CHAR(10) | Sim | `'db'` (SQLite) ou `'csv'` |
| `local_path` | TEXT | Sim | Nome do arquivo relativo ao `backup_dir` (null = sem arquivo local); caminho absoluto resolvido em `backup::get_local_path()` |
| `external_service` | CHAR(40) | Sim | Nome do serviço externo (ex: `'s3'`) ou null |
| `external_uri` | TEXT | Sim | URI no serviço externo (ex: `s3://bucket/key`) |
| `external_customdata` | TEXT | Sim | JSON com metadados extras do upload externo |
| `restored` | TINYINT | Não | `1` = registros reinseridos no logstore |
| `deleted_at` | INT | Não | `0` = ativo; timestamp = momento da exclusão lógica |
| `timecreated` | INT | Não | Timestamp de criação do registro de backup |
| `timemodified` | INT | Não | Timestamp de última modificação |

**Índices:**

| Nome | Colunas | Propósito |
|------|---------|-----------|
| `idx_starttime_endtime` | `starttime, endtime` | Busca por sobreposição de intervalo de tempo |
| `idx_deleted_at` | `deleted_at` | Filtrar backups ativos vs. deletados |
| `idx_fileformat` | `fileformat` | Diferenciar SQLite de CSV na busca |

### Localização física dos arquivos

Os arquivos de backup **não** usam o file storage do Moodle. São gerenciados diretamente no sistema de arquivos:

- **Backup original:** `{backup_dir}/{starttime}_{endtime}.{format}`
- **Cache de arquivo remoto:** `{cache_dir}/{starttime}_{endtime}.{format}`

Os diretórios são protegidos com `.htaccess` (`deny from all`) para impedir acesso direto pelo web server.

---

## 3. Configurações

Acessível em: `Administração do site > Ferramentas > Arquivador de Logstore`

### Geral

| Chave | Padrão | Descrição |
|-------|--------|-----------|
| `enabled` | `1` | Liga/desliga o plugin inteiro |
| `backup_format` | `db` | Formato de arquivo: `db` (SQLite pesquisável) ou `csv` (só arquivo) |
| `max_records_per_file` | `500000` | Máximo de registros por arquivo antes de dividir |
| `log_lifetime` | `26 semanas` | Idade mínima para um registro ser arquivado |

### Armazenamento

| Chave | Padrão | Descrição |
|-------|--------|-----------|
| `backup_dir` | `{moodledata}/tool_stdlogarchiver/` | Diretório dos arquivos de backup |
| `cache_dir` | `{moodledata}/tool_stdlogarchiver_cache/` | Diretório de cache de arquivos remotos |
| `cache_ttl` | `86400s` (1 dia) | Tempo de validade de um arquivo em cache |

### Retenção e Purga

| Chave | Padrão | Descrição |
|-------|--------|-----------|
| `backup_retention_ttl` | `0` (desativado) | Backups mais velhos que este TTL são auto-deletados |

### Armazenamento Externo

| Chave | Padrão | Descrição |
|-------|--------|-----------|
| `external_backup_service` | `''` (nenhum) | Serviço externo: vazio ou `'s3'` |
| `external_migration_delay` | `0` | Atraso mínimo após arquivamento para iniciar upload |
| `delete_local_after_external` | `0` | Remove arquivo local após upload externo bem-sucedido |

### AWS / S3

| Chave | Descrição |
|-------|-----------|
| `aws_region` | Região AWS (ex: `us-east-1`) |
| `aws_key` | Access Key ID |
| `aws_secret` | Secret Access Key (campo de senha mascarado na UI) |
| `s3_bucket` | Nome do bucket S3 |
| `s3_folder` | Prefixo de pasta dentro do bucket |

---

## 4. Controle de Acesso

Todas as capabilities são concedidas no contexto `CONTEXT_SYSTEM`.

| Capability | Risco | Concedida por padrão a |
|-----------|-------|------------------------|
| `tool/stdlogarchiver:view` | `RISK_PERSONAL` | Manager |
| `tool/stdlogarchiver:delete` | `RISK_DATALOSS` | Manager |
| `tool/stdlogarchiver:restore` | `RISK_MANAGETRUST` | Manager |
| `tool/stdlogarchiver:config` | `RISK_CONFIG` | Manager |

---

## 5. Formatos de Arquivo

### SQLite (`.db`) — padrão recomendado

- Banco SQLite com uma tabela `logs` cujas colunas espelham exatamente o schema do `logstore_standard_log`
- **Indexado:** índices B-tree criados após todos os inserts em `timecreated`, `userid`, `courseid`, `eventname`, `relateduserid`
- **Pesquisável:** filtros são executados como SQL diretamente no arquivo, sem varredura em PHP
- **Imutável:** aberto com `immutable=1` nas queries de leitura — sem lock, sem WAL
- Quando enviado ao S3, é comprimido para `.db.gz` (gzip nível 9)

**Pragmas de escrita (alta performance):**
```
page_size     = 8192
synchronous   = OFF
journal_mode  = MEMORY
temp_store    = MEMORY
cache_size    = -65536   (64 MB)
```

**Pragmas restaurados após finalização:**
```
synchronous  = NORMAL
journal_mode = WAL
```

Após `finalize()`, o arquivo recebe permissões `0444` (somente leitura).

### CSV (`.csv`) — somente arquivo

- Cabeçalho com os nomes das colunas na primeira linha
- Separado por vírgula via `SplFileObject::fputcsv()`
- **Não pesquisável** — não pode ser consultado pela funcionalidade de busca
- Não é comprimido no S3

---

## 6. Modelo de Dados — `backup`

A classe `tool_stdlogarchiver\models\backup` estende `core\persistent` do Moodle e é a entidade central do plugin.

### Propriedades de estado

```
is_deleted()      → deleted_at > 0
was_restored()    → restored == 1
is_restoring()    → restore_backup_task está na fila para este ID
is_searchable()   → fileformat == 'db'
has_external()    → external_service não está vazio
local_file_exists() → local_path não é null E arquivo existe no disco
is_cached()       → arquivo em cache_dir existe E (now - mtime) < cache_ttl
```

### Resolução de caminho para leitura (`get_path_for_query()`)

```
1. local_file_exists()? → retorna local_path
2. cache existe e não expirou? → touch(cache_path) + retorna cache_path
3. cache existe mas expirou? → unlink(cache_path) + cai no 4
4. → lança moodle_exception('backupfilenotavailable')
   → chamador deve enfileirar cache_download_task
```

### Nome do arquivo

```
{starttime}_{endtime}.{fileformat}
Exemplo: 1704067200_1704153599.db
```

Os timestamps são os `timecreated` do primeiro e último registro do lote, não timestamps do momento de criação.

---

## 7. Processo: Arquivamento

**Tarefa:** `archive_task` | **Horário:** diariamente às 01:00

### Watermark

A tarefa calcula o watermark uma vez no início de cada execução:

```sql
SELECT COALESCE(MAX(lastid), 0) FROM {tool_stdlogarchiver_backups}
WHERE deleted_at = 0
```

O maior `lastid` entre todos os backups existentes é o piso absoluto — a tarefa usa `id > :min_id` em todas as queries e nunca processa IDs abaixo desse valor.

Isso protege automaticamente os registros restaurados: quando um backup é restaurado, seus registros voltam ao logstore com os IDs originais. Como esses IDs estão abaixo do watermark, **jamais serão re-arquivados** por nenhuma execução futura. Nenhum estado extra é gravado em config — a própria tabela de backups é a fonte da verdade.

### Fluxo detalhado

```
archive_task::execute()
│
├─ 1. Verificar plugin habilitado → sair se não
├─ 2. Elevar limites: MEMORY_HUGE + raise time limit
├─ 3. Calcular cutoff = now - log_lifetime
├─ 4. min_id = MAX(lastid) FROM backups WHERE deleted_at=0  ← watermark
│
└─ 5. Loop externo (roda até esgotar todos os dias arquiváveis):
       │
       ├─ SELECT MIN(timecreated) FROM logstore
       │    WHERE timecreated < cutoff AND id > min_id
       ├─ Se null → break (nada mais a arquivar)
       │
       ├─ Calcular limites do dia UTC:
       │    day_start = floor(min_tc / 86400) * 86400
       │    day_end   = day_start + 86400
       │
       └─ Loop interno (sem limite de tempo — arquiva o dia inteiro):
              │
              ├─ SELECT * FROM logstore WHERE
              │    timecreated BETWEEN day_start AND day_end
              │    AND timecreated < cutoff
              │    AND id > min_id
              │    ORDER BY id ASC LIMIT max_records_per_file
              │
              ├─ Se vazio → break (dia concluído)
              │
              └─ write_chunk($records):
                     │
                     ├─ filepath = backup_dir/{first.timecreated}_{last.timecreated}.{format}
                     ├─ writer = new sqlite_writer (ou csv_writer)
                     ├─ Para cada registro:
                     │    other = to_json(other)  ← normaliza serialized→JSON
                     │    writer->append(record)
                     ├─ writer->finalize()
                     │    SQLite: COMMIT + 5 CREATE INDEX + VACUUM + chmod 0444
                     │
                     ├─ INSERT INTO tool_stdlogarchiver_backups (metadados)
                     │    ← min_id avança para end($records)->id
                     │
                     └─ DELETE logstore WHERE id IN (ids explícitos, chunks de 1000)
                            ← IDs explícitos evitam race condition do BETWEEN
```

### Campo `other`

O campo `other` do logstore pode estar em PHP serialized ou JSON dependendo da versão do Moodle. O plugin sempre normaliza para JSON ao arquivar via `logstored_other_trait::to_json()`, garantindo compatibilidade de leitura futura.

### Como eliminar registros restaurados do logstore

Registros restaurados ficam protegidos pelo watermark e nunca são re-arquivados automaticamente. Para removê-los do logstore, o admin usa a ação **"Desfazer restauração"** na lista de backups (ver seção 14), que deleta explicitamente os registros e marca o backup como `restored=0`. O arquivo de backup permanece intacto.

---

## 8. Processo: Migração Externa (S3)

**Tarefa:** `external_migration_task` | **Horário:** diariamente às 03:00

### Condições para execução

- Serviço externo configurado e `is_enabled()` retorna verdadeiro
- `external_migration_delay > 0` (configuração obrigatória)

### Fluxo

```
external_migration_task::execute()
│
├─ 1. Resolver serviço ativo via config::get_external_backup_service()
├─ 2. cutoff = now - external_migration_delay
│
└─ 3. SELECT backups WHERE:
          timecreated < cutoff        ← aguardou o delay
          AND deleted_at = 0
          AND external_service IS NULL ← ainda não migrado
          AND local_path IS NOT NULL   ← tem arquivo local
       ORDER BY timecreated ASC
│
   Para cada backup:
   ├─ Verificar local_file_exists() → pular se ausente
   │
   ├─ service->upload(backup):
   │    SQLite: gzip(local_path → tmp.gz) → S3.putObject(key=folder/name.db.gz)
   │    CSV:    S3.putObject(key=folder/name.csv) diretamente
   │    Retorna URI: "s3://bucket/folder/filename"
   │
   ├─ backup.external_service = 's3'
   ├─ backup.external_uri     = "s3://..."
   ├─ backup.external_customdata = {original_size, service, migrated_at}
   ├─ backup.save()
   │
   └─ Se delete_local_after_external:
          backup.delete_local_file()  ← unlink + local_path = null
```

### Chave S3

```
{s3_folder}/{starttime}_{endtime}.{format}[.gz]

Exemplos:
  backups/1704067200_1704153599.db.gz   ← SQLite comprimido
  backups/1704067200_1704153599.csv     ← CSV sem compressão
```

### Compressão

O `compression_helper` usa `gzencode()` em chunks de 512 KB (nível 9). O arquivo `.db.gz` no S3 é transparentemente descomprimido em cache quando necessário.

---

## 9. Processo: Limpeza de Cache

**Tarefa:** `cache_cleanup_task` | **Horário:** diariamente às 04:00

```
cache_cleanup_task::execute()
│
└─ Escanear cache_dir com FilesystemIterator:
   Para cada arquivo:
   ├─ getMTime() < (now - cache_ttl)? → unlink()
   └─ Caso contrário, manter
```

O cache armazena cópias temporárias de arquivos remotos baixados para busca. Cada `touch()` ao abrir um arquivo em cache renova o TTL efetivamente.

---

## 10. Processo: Purga Permanente

**Tarefa:** `backup_purge_task` | **Horário:** diariamente às 05:00

A purga acontece em dois passes sequenciais:

### Passe 1 — Auto-soft-delete por TTL de retenção

```
Se backup_retention_ttl > 0:
   SELECT backups WHERE timecreated < (now - backup_retention_ttl)
                    AND deleted_at = 0
                    AND restored = 0

   Para cada registro:
   └─ backup.deleted_at = now()  ← soft-delete automático
      backup.save()
```

### Passe 2 — Purga física de todos os soft-deletados

```
SELECT backups WHERE deleted_at > 0
                 AND (local_path IS NOT NULL OR external_service IS NOT NULL)

Para cada registro:
├─ local_file_exists()? → unlink(local_path)
│
├─ has_external()?
│    service = config::get_external_backup_service(external_service)
│    service->delete(external_uri)  ← S3.deleteObject
│
├─ backup.local_path         = null
├─ backup.external_service   = null
├─ backup.external_uri       = null
├─ backup.external_customdata = null
└─ backup.save()
```

**O registro no banco de dados é preservado** mesmo após a purga física, funcionando como trilha de auditoria. Apenas as referências a arquivos são zeradas.

---

## 11. Processo: Busca em Logs Arquivados

**Página:** `/admin/tool/stdlogarchiver/search/index.php`

A busca opera exclusivamente em backups com formato SQLite (`fileformat = 'db'`). Backups CSV são contabilizados mas não consultados.

### Filtros disponíveis

| Campo | Tipo | Comportamento |
|-------|------|---------------|
| `starttime` | DateTime | Obrigatório; inicio do intervalo |
| `endtime` | DateTime | Obrigatório; fim do intervalo; máx. 31 dias |
| `eventname` | Autocomplete | Classe PHP completa do evento |
| `userid` | INT | ID do usuário que gerou o evento |
| `relateduserid` | INT | ID de usuário relacionado |
| `courseid` | INT | ID do curso |
| `origin` | Select | `web`, `cli`, `ws` ou todos |

### Fluxo da busca

```
search_service::search(starttime, endtime, filters)
│
├─ 1. get_sqlite_backups(starttime, endtime):
│       SELECT WHERE fileformat='db'
│                AND deleted_at=0
│                AND restored=0
│                AND starttime < :endtime AND endtime > :starttime
│       ORDER BY starttime ASC
│
├─ 2. count_csv_backups() → registra skipped_csv (informativo)
│
└─ 3. Para cada backup SQLite:
       │
       ├─ local_file_exists()?
       │    → path = local_path
       │
       ├─ is_cached()?
       │    → touch(cache_path)  ← renova TTL
       │    → path = cache_path
       │
       ├─ has_external() (sem arquivo local ou cache)?
       │    → cache_download_task::create_and_enqueue(backupid)
       │    → adiciona a pending[]
       │    → continue  ← pula este backup agora
       │
       ├─ Nenhuma opção disponível?
       │    → adiciona a unavailable[]
       │    → continue
       │
       └─ sqlite_reader::search(starttime, endtime, filters):
               SQL: SELECT * FROM logs
                    WHERE timecreated BETWEEN :start AND :end
                      [AND userid = :userid]
                      [AND courseid = :courseid]
                      [AND eventname = :eventname]
                      [AND relateduserid = :relateduserid]
                      [AND origin = :origin]
               → Generator de stdClass
               → record.backupid = backup.id
               → adiciona a results[]
               → adiciona backup a searched[]
```

### Retorno do `search_service::search()`

```php
[
    'results'     => object[],  // registros encontrados
    'searched'    => backup[],  // backups consultados com sucesso
    'skipped_csv' => int,       // contagem de backups CSV ignorados
    'pending'     => backup[],  // aguardando download do remoto
    'unavailable' => backup[],  // sem arquivo local nem externo
]
```

### Comportamento assíncrono

Se houver backups em `pending`, o resultado parcial é exibido imediatamente com uma notificação de aviso. O usuário deve aguardar o cron executar os `cache_download_task` (ver seção 12) e repetir a busca.

---

## 12. Processo: Download de Cache Assíncrono

**Tarefa:** `cache_download_task` | **Tipo:** adhoc (sob demanda)

Criada automaticamente pelo `search_service` quando um backup está apenas no armazenamento externo.

### Fluxo

```
cache_download_task::execute()
│
├─ 1. Carregar backup por ID
├─ 2. local_file_exists() OR is_cached()? → return (já disponível)
├─ 3. has_external()? → senão lança noexternalbackup
├─ 4. get_external_backup_service(external_service) → senão lança noexternalservice
│
└─ 5. external_uri termina em '.gz'?
       ├─ Sim (SQLite):
       │    gz_tmp = cache_path + '.gz.tmp'
       │    service->download_to_path(external_uri, gz_tmp)
       │    compression_helper::gunzip(gz_tmp, cache_path)
       │    unlink(gz_tmp)
       └─ Não (CSV ou outro):
            service->download_to_path(external_uri, cache_path)
│
└─ chmod(cache_path, 0444)
```

### Deduplicação

`create_and_enqueue()` verifica `is_enqueued()` antes de enfileirar:

```php
SELECT FROM task_adhoc
WHERE classname = ? AND component = ? AND customdata = ?
```

Se já existe uma tarefa para o mesmo `backupid`, nenhuma nova tarefa é criada.

---

## 13. Processo: Restauração de Backup

A restauração reinserí todos os registros de um backup no `logstore_standard_log`, preservando os IDs originais.

### Ação do usuário (síncrona)

```
actions_controller::restore_backup(backupid)
│
├─ require_sesskey()
├─ require_capability(':restore')
├─ backup = new backup(backupid)
├─ Validações:
│    is_deleted()?    → exception backup_deleted
│    is_restoring()?  → exception already_restoring
│    was_restored()?  → exception already_restored
│
└─ backup->create_restore_task()
     └─ restore_backup_task::create_and_enqueue(backupid) → enfileira adhoc
```

### Execução em background (restore_backup_task)

```
restore_backup_task::execute()
│
├─ raise MEMORY_EXTRA + raise time limit
│
└─ backup->restore()
     └─ backup_restorer::execute()
          │
          ├─ reader = backup->get_reader()  ← sqlite_reader ou csv_reader
          │    get_path_for_query() → local ou cache
          │
          └─ Para cada registro via get_contents_generator():
                 ├─ format_record(record):
                 │    cast de strings numéricas para int
                 │    campo 'other': to_serialized() se logstore usa serialized,
                 │                   to_json() se usa JSON
                 └─ DB->import_record(logstore_table, record)
                        ← insere com ID original preservado
          │
          └─ backup.restored = true
             backup.save()
```

**O ID original é preservado.** Se um registro com aquele ID já existir, `import_record` falhará — o plugin não verifica duplicatas, assumindo que `undo_restore` foi executado antes.

---

## 14. Processo: Desfazer Restauração

Remove do logstore os registros que foram restaurados. É também o mecanismo de "re-delete" para registros restaurados que o admin deseja eliminar — necessário porque o watermark do `archive_task` os protege de re-arquivamento automático.

### Ação do usuário

```
actions_controller::unrestore_backup(backupid)
│
├─ Validações: sesskey, capability, deleted?, restoring?, !restored?
└─ backup->create_unrestore_task()
     └─ unrestore_backup_task::create_and_enqueue(backupid)
```

### Execução em background (unrestore_backup_task)

```
backup->undo_restore()
│
├─ DELETE FROM logstore_standard_log
│    WHERE id BETWEEN firstid AND lastid
│
├─ backup.restored = false
└─ backup.save()
```

O arquivo de backup **não é removido** — apenas os registros reinseridos no logstore são deletados. O backup continua disponível para restaurações futuras.

**Nota:** Usa `BETWEEN` (não IDs explícitos) pois o objetivo é remover toda a faixa de IDs restaurados — não há risco de race condition aqui porque o logstore não insere registros nessa faixa enquanto a restauração estiver ativa.

---

## 15. Processo: Download de Arquivo

**Endpoint:** `download.php?id={backupid}&sesskey={sesskey}`

```
download.php
│
├─ require_login()
├─ require_sesskey()
├─ require_capability(':view')
├─ backup = new backup(id)
│
├─ Tentar local_file_exists() → path = local_path
├─ Senão tentar get_path_for_query():
│    local → cache válido → exception (sem arquivo)
│
└─ header('Content-Disposition: attachment; filename=...')
   header('Content-Type: application/octet-stream')
   header('Content-Length: ' . filesize(path))
   readfile(path)
```

CSV e SQLite são servidos como `application/octet-stream` para download direto. O arquivo local não precisa ser descomprimido — é o `.db` original.

---

## 16. Processo: Exclusão pelo Admin

A exclusão é sempre **lógica** (soft-delete). A exclusão física acontece apenas no `backup_purge_task`.

```
actions_controller::delete_backup(backupid)
│
├─ require_sesskey()
├─ require_capability(':delete')
├─ backup = new backup(backupid)
├─ is_deleted()? → exception backup_deleted
│
└─ backup->soft_delete()
     └─ backup.deleted_at = time()
        backup.save()
```

O backup some da listagem (que filtra por `deleted_at = 0`), mas o arquivo físico permanece até o próximo `backup_purge_task`.

---

## 17. Interface de Usuário

### Página: Lista de Backups (`index.php`)

- URL: `/admin/tool/stdlogarchiver/index.php?page=N`
- 25 backups por página, filtrados por `deleted_at = 0`
- Ordenação padrão: `id DESC`

**Colunas da tabela:**

| Coluna | Fonte |
|--------|-------|
| ID | `backup.id` |
| Período início | `userdate(starttime)` |
| Período fim | `userdate(endtime)` |
| Formato | `fileformat` (CSV ou DB) |
| Criado em | `userdate(timecreated)` |
| Pesquisável | `is_searchable()` → Sim/Não |
| Externo | `external_service` ou Não |
| Restaurado | `was_restored()` → Sim/Não |
| Restaurando | `is_restoring()` → Sim/Não |
| Excluído em | `userdate(deleted_at)` ou Não |
| Ações | Botões condicionais |

**Ações por linha:**

| Ação | Condição de exibição |
|------|---------------------|
| Download | `!is_deleted() AND (local_file_exists() OR is_cached())` |
| Restaurar | `!is_deleted() AND !was_restored() AND !is_restoring()` |
| Desfazer restauração | `was_restored()` |
| Excluir | `!is_deleted()` |

Todas as ações destrutivas (restaurar, excluir, desfazer) usam `confirm_action` com diálogo de confirmação JavaScript.

### Página: Busca (`search/index.php`)

- URL: `/admin/tool/stdlogarchiver/search/index.php`
- Formulário `moodleform` com validação server-side
- Notificações de aviso para backups `pending` ou `skipped_csv`

**Resultados:**

- Tabela `search_results_table`: colunas = `backupid` + todas as colunas do logstore_standard_log
- Tabela `search_info`: mostra quais backups foram consultados e seu status (Pesquisado / Pendente / Não)

---

## 18. Camada de Armazenamento Externo

### Interface (`external_backup_service_interface`)

```php
is_enabled(): bool
get_name(): string
upload(backup $backup): string        // retorna URI completa
download_to_path(string $uri, string $dest): void
delete(string $uri): void
```

Qualquer novo serviço de armazenamento (Google Cloud Storage, Azure Blob, etc.) implementa esta interface e se registra em `config::get_external_backup_services()`.

### Implementação S3 (`external_s3_backup_service`)

**`is_enabled()`:** Exige `external_backup_service='s3'` + `aws_key` + `aws_secret` + `s3_bucket` + `s3_folder`.

**`upload()`:**
1. SQLite: `gzip(local_path → tmp.gz, level=9)` → `S3::putObject(key=folder/name.db.gz)` → `unlink(tmp)`
2. CSV: `S3::putObject(key=folder/name.csv)` diretamente
3. Retorna `s3://bucket/key`

**`download_to_path()`:** `S3::getObject(SaveAs=dest_path)`

**`delete()`:** `S3::deleteObject`

**Parse de URI:**
```
"s3://my-bucket/backups/1704067200_1704153599.db.gz"
 ↓ parse_url()
 host = "my-bucket"
 path = "/backups/1704067200_1704153599.db.gz"
```

---

## 19. Tarefas Agendadas — Resumo

| Tarefa | Tipo | Horário | Função |
|--------|------|---------|--------|
| `archive_task` | Scheduled | 01:00 | Move logs antigos do banco para arquivo |
| `external_migration_task` | Scheduled | 03:00 | Upload de arquivos locais para S3 |
| `cache_cleanup_task` | Scheduled | 04:00 | Remove arquivos de cache expirados |
| `backup_purge_task` | Scheduled | 05:00 | Purga física de backups soft-deletados |
| `restore_backup_task` | Adhoc | Sob demanda | Restaura backup para o logstore |
| `unrestore_backup_task` | Adhoc | Sob demanda | Remove registros restaurados do logstore |
| `cache_download_task` | Adhoc | Sob demanda | Baixa arquivo remoto para cache local |

As tarefas adhoc de restore são `blocking=true`, ou seja, o Moodle não executa outras tarefas blocking em paralelo durante a restauração.

---

## 20. Privacidade e GDPR

`classes/privacy/provider.php` implementa `core_privacy\local\metadata\provider`.

**Dados pessoais armazenados:**

- Arquivos de backup contêm todos os campos do `logstore_standard_log`, incluindo `userid`, `ip` (via `origin`), `relateduserid` e dados de contexto de acesso
- Backups no S3 contêm os mesmos dados

**Limitações atuais:** O plugin declara metadados mas **não implementa** `plugin_provider`, ou seja, não há suporte a:
- Exportação de dados por usuário
- Exclusão de dados por usuário
- `get_users_in_context()`

Para compliance completo com GDPR, esses métodos precisariam ser implementados (exigiria reescrita dos arquivos SQLite/CSV para anonimizar registros de usuários específicos).

---

## 21. Dependências Externas

### AWS SDK PHP

Gerenciado via Composer. O arquivo `vendor/autoload.php` é carregado sob demanda apenas nos métodos que usam S3, evitando overhead em execuções sem armazenamento externo.

```json
{
    "require": {
        "php": ">=8.1",
        "aws/aws-sdk-php": "^3.300"
    }
}
```

### Dependências Moodle

- `logstore_standard` (declarado em `version.php`)
- Moodle 4.2+ (`$plugin->requires = 2022041900`)

### Dependências PHP

- `SQLite3` extension (para formato `.db`)
- `zlib` extension (para gzip/gunzip)
- `SplFileObject` (para CSV)

---

*Documento gerado em 2026-04-18. Descreve o estado do plugin versão 2024120101.*
