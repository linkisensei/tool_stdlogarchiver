# PLAN.md — Rework: tool_stdlogarchiver (v2)

> Decisões tomadas e o que precisa ser implementado.
> Ver `ANALYSIS.md` para o estado atual e `REWORK.md` para contexto de melhorias gerais.
> **Esta versão substitui o PLAN.md anterior** — arquitetura revisada com novas decisões.

---

## Índice

1. [Decisões de Design](#1-decisões-de-design)
2. [O que Muda — Visão Geral](#2-o-que-muda--visão-geral)
3. [Banco de Dados — Schema Único](#3-banco-de-dados--schema-único)
4. [Armazenamento e Ciclo de Vida](#4-armazenamento-e-ciclo-de-vida)
5. [Nomenclatura dos Arquivos](#5-nomenclatura-dos-arquivos)
6. [Formatos Suportados](#6-formatos-suportados)
7. [SQLite — Otimizações de Build](#7-sqlite--otimizações-de-build)
8. [SQLite — Leitura Somente](#8-sqlite--leitura-somente)
9. [Migração Externa (external_migration_task)](#9-migração-externa-external_migration_task)
10. [Busca — Síncrona e Assíncrona](#10-busca--síncrona-e-assíncrona)
11. [Limpeza de Cache (cache_cleanup_task)](#11-limpeza-de-cache-cache_cleanup_task)
12. [Archive Task — Nova Lógica](#12-archive-task--nova-lógica)
13. [Configurações — Novas e Modificadas](#13-configurações--novas-e-modificadas)
14. [Estrutura de Classes](#14-estrutura-de-classes)
15. [Tasks Agendadas](#15-tasks-agendadas)
16. [O que Remover](#16-o-que-remover)
17. [Decisões Pendentes](#17-decisões-pendentes)

---

## 1. Decisões de Design

| # | Decisão | Escolha |
|---|---------|---------|
| 1 | Sistema de armazenamento de arquivos | Diretório próprio no moodledata — **sem Moodle file storage** |
| 2 | Formatos suportados | **SQLite** (buscável) e **CSV** (archive-only) |
| 3 | Nomenclatura dos arquivos | `{starttime}_{endtime}.{ext}` — timestamps do conteúdo real |
| 4 | Separação de arquivos | Por **dia de calendário** com cap de registros configurável |
| 5 | SQLite para busca | Índices B-tree internos — sem tabela de índice no banco |
| 6 | SQLite abertura | Flag `immutable=1` — zero overhead de locking |
| 7 | Compressão para external storage | SQLite → `.db.gz` (gzip nível 9) antes do upload |
| 8 | Busca com arquivo remoto | **Assíncrona via adhoc task** — resultados parciais imediatos |
| 9 | Migração para external storage | `external_migration_task` genérico (não específico de S3) |
| 10 | Schema do banco | **Tabela única** com `deleted_at` e colunas de local e external storage |
| 11 | Limpeza de cache | `cache_cleanup_task` agendada — varre diretório e deleta por `mtime` |
| 12 | Deleção permanente | `backup_purge_task` com TTL separado — apaga arquivos local + remoto |
| 13 | Soft-delete | `deleted_at` timestamp (0 = ativo) — substituiu `deleted` boolean |
| 14 | Proteção na restauração | Archive task exclui IDs pertencentes a backups com `restored=1` |
| 15 | Plugin nunca foi usado | Sem preocupação com migração de dados existentes |

---

## 2. O que Muda — Visão Geral

### Removido completamente
- Integração com Moodle file storage (`stored_file`, `pathnamehash`, `get_file_storage`)
- `lib.php::tool_stdlogarchiver_pluginfile()` — substituída por `download.php`
- Tabela `tool_stdlogarchiver_ext_bkps` — substituída por colunas na tabela principal
- Dependência do AWS SDK em `/libs/` — migrar para Composer
- Lógica de arquivamento por count (`records_per_file` como divisor)
- Limite de 5 backups por "página de busca"

### Adicionado
- **Tabela única** com `local_path`, `external_service`, `external_uri`, `external_customdata`
- Diretório próprio de backups (`$CFG->dataroot/tool_stdlogarchiver/` ou configurável)
- Diretório de cache para arquivos externos (configurável, default em `sys_get_temp_dir()`)
- `sqlite_writer.php` com todas as otimizações de build
- `sqlite_reader.php` com abertura imutável, busca SQL e header validation
- `external_migration_task.php` — migração genérica local → external storage
- `cache_download_task.php` — adhoc task para baixar arquivo remoto para cache
- `cache_cleanup_task.php` — limpeza periódica de cache expirado
- `download.php` — serve arquivos diretamente do diretório de backups
- Busca assíncrona: retorna resultados parciais + lista de backups "pendentes de download"
- Compressão gzip na migração para external storage

### Modificado
- `csv_writer.php` — escreve direto no diretório de backups (sem Moodle file storage)
- `csv_reader.php` — lê de path direto
- `backup.php` — schema novo, métodos de path local e external
- `archive_task.php` — lógica day-based com split por cap
- `search_service.php` — busca SQL nos SQLite; async para arquivos remotos
- `external_s3_backup_service.php` — adaptar para upload com gzip, download para path
- `config.php` — novos getters
- `settings.php` — novas configurações
- `db/install.xml` — schema novo (tabela única, sem ext_bkps)
- `db/tasks.php` — tasks atualizadas

---

## 2b. Deleção Permanente e Proteção na Restauração

### Deleção Permanente (backup_purge_task)

A deleção real de backups tem **dois critérios independentes**:

1. **Soft-deleted pelo usuário**: `deleted_at > 0` → arquivos físicos ainda podem existir
2. **Auto-purge por TTL**: backup mais velho que `backup_retention_ttl` e `restored = 0`

O `backup_purge_task` (diário 05:00 AM):
- Encontra backups com `deleted_at > 0` que ainda têm arquivo local ou externo → apaga os arquivos
- Encontra backups com `timecreated < (now - backup_retention_ttl)` e `restored = 0` e `deleted_at = 0` → soft-deletes e apaga os arquivos na mesma passagem
- Para cada backup com external storage: chama `service::delete(external_uri)`
- Após apagar: `local_path = null`, `external_service = null`, `external_uri = null`

O TTL de retenção (`backup_retention_ttl`) é configurável separadamente de:
- `log_lifetime` — retenção no logstore antes de arquivar
- `cache_ttl` — retenção de arquivos de cache

### Proteção na Restauração (archive_task)

Quando um backup é restaurado (`restored = 1`), seus registros voltam para o `logstore_standard_log` com seus IDs originais. O archive task NÃO DEVE re-arquivar esses registros.

**Solução:** Antes de selecionar registros de um dia, buscar ranges de IDs de backups restaurados e excluí-los da query:

```php
// Busca ranges de backups restaurados (geralmente < 5 ao mesmo tempo)
$restored_ranges = $DB->get_records_sql(
    "SELECT firstid, lastid FROM {tool_stdlogarchiver_backups}
     WHERE restored = 1 AND deleted_at = 0",
    []
);

// Monta exclusão
$exclusions = '';
$excl_params = [];
$i = 0;
foreach ($restored_ranges as $r) {
    $exclusions .= " AND NOT (id BETWEEN :rfirst{$i} AND :rlast{$i})";
    $excl_params["rfirst{$i}"] = (int)$r->firstid;
    $excl_params["rlast{$i}"]  = (int)$r->lastid;
    $i++;
}

// Query usa $exclusions na cláusula WHERE
```

---

## 3. Banco de Dados — Schema Único

### Tabela única: `tool_stdlogarchiver_backups`

Substitui tanto a tabela antiga `tool_stdlogarchiver_backups` quanto `tool_stdlogarchiver_ext_bkps`.

| Campo | Tipo | Nulo | Descrição |
|-------|------|------|-----------|
| `id` | INT(10) | Não | PK |
| `firstid` | INT(10) | Não | Primeiro ID de log neste backup |
| `lastid` | INT(10) | Não | Último ID de log neste backup |
| `starttime` | INT(10) | Não | timecreated do primeiro registro |
| `endtime` | INT(10) | Não | timecreated do último registro |
| `fileformat` | CHAR(10) | Sim | 'db' ou 'csv' |
| `local_path` | TEXT | Sim | Caminho absoluto do arquivo local (null se não existe localmente) |
| `external_service` | CHAR(40) | Sim | Nome do serviço externo: 's3', 'gcs' (null se não migrado) |
| `external_uri` | TEXT | Sim | URI no serviço externo: `s3://bucket/folder/file.db.gz` |
| `external_customdata` | TEXT | Sim | JSON com metadados extras (tamanhos, compressão, etc.) |
| `restored` | INT(1) | Não | 1 = logs restaurados para logstore |
| `deleted_at` | INT(10) | Não | 0 = ativo; timestamp = soft-deleted |
| `timecreated` | INT(10) | Não | Timestamp de criação |
| `timemodified` | INT(10) | Não | Timestamp de modificação |

> **Nota:** `deleted` boolean foi substituído por `deleted_at` (INT com DEFAULT 0). `deleted_at = 0` significa ativo; `deleted_at > 0` significa soft-deleted (timestamp do momento da deleção).

**Índices:**
```xml
<INDEX NAME="idx_starttime_endtime" UNIQUE="false" FIELDS="starttime, endtime"/>
<INDEX NAME="idx_deleted_at"        UNIQUE="false" FIELDS="deleted_at"/>
<INDEX NAME="idx_fileformat"        UNIQUE="false" FIELDS="fileformat"/>
```

### Eliminação da tabela ext_bkps

A tabela `tool_stdlogarchiver_ext_bkps` é **removida**. As informações de external storage vivem agora nas colunas `external_service`, `external_uri`, `external_customdata` da tabela principal.

### Derivação do local do arquivo

O `local_path` é armazenado no banco (não derivado) porque:
- O diretório de backup pode ser reconfigurado
- Permite distinguir "arquivo local original" de "arquivo de cache" (que não é armazenado no banco)

### Cache — não é armazenado no banco

O cache de arquivos baixados do external storage é gerenciado puramente pelo filesystem. O path do cache é **derivado** do `external_uri`:

```php
public function get_cache_path(): ?string {
    if (!$this->has_external()) {
        return null;
    }
    // Extract filename from URI, strip .gz extension
    $uri_filename = basename($this->get('external_uri')); // e.g. "1704067200_1704153599.db.gz"
    $local_name = preg_replace('/\.gz$/', '', $uri_filename); // "1704067200_1704153599.db"
    return config::get_cache_dir() . '/' . $local_name;
}

public function is_cached(): bool {
    $cache_path = $this->get_cache_path();
    if (!$cache_path || !file_exists($cache_path)) {
        return false;
    }
    $age = time() - filemtime($cache_path);
    return $age < config::get_cache_ttl();
}
```

---

## 4. Armazenamento e Ciclo de Vida

### Diretório de backups

```
$CFG->dataroot/tool_stdlogarchiver/    ← padrão
```

Configurável via settings. Criado na instalação e no upgrade.
Contém `.htaccess` com `deny from all` por proteção.

### Ciclo de vida de um arquivo de backup

```
1. archive_task cria o arquivo local
   → {backup_dir}/{starttime}_{endtime}.db   (SQLite)
   → {backup_dir}/{starttime}_{endtime}.csv  (CSV)
   → Registro no DB: local_path = filepath, external_service = null

2. Arquivo permanece local pelo tempo configurado em external_migration_delay

3. external_migration_task faz upload:
   → SQLite: comprime para .db.gz, faz upload
   → CSV: faz upload como .csv
   → Atualiza: external_service, external_uri, external_customdata
   → Se delete_local_after_external = true:
      → Deleta arquivo local
      → Atualiza: local_path = null

4. Para BUSCA em arquivo no external storage:
   → search_service detecta que não há local e não há cache
   → Cria cache_download_task (adhoc)
   → Retorna backup como "pending" ao usuário
   → Usuário pode re-executar a busca após o download

5. cache_download_task baixa o arquivo:
   → Download do .db.gz para temp
   → Descomprime para {cache_dir}/{filename}.db
   → Deleta o temp .gz
   → Cache fica disponível para próxima busca

6. cache_cleanup_task remove caches expirados:
   → Varre cache_dir por mtime
   → Deleta arquivos com mtime < (now - cache_ttl)
```

### Diretório de cache

```
Padrão: sys_get_temp_dir() . '/tool_stdlogarchiver_cache/'
Configurável via settings.
```

---

## 5. Nomenclatura dos Arquivos

```
{starttime}_{endtime}.{ext}
```

- `starttime` = `timecreated` do **primeiro** registro no arquivo
- `endtime`   = `timecreated` do **último** registro no arquivo
- `ext`       = `db` (SQLite) ou `csv` (CSV)

O filename é **derivado** das colunas `starttime`, `endtime` e `fileformat`:

```php
public function get_filename(): string {
    return sprintf('%d_%d.%s',
        $this->get('starttime'),
        $this->get('endtime'),
        $this->get('fileformat')
    );
}
```

O `local_path` é armazenado no banco (não derivado) para resiliência a mudança de diretório.

---

## 6. Formatos Suportados

### SQLite (`.db`) — Buscável

- Armazena todos os campos do `logstore_standard_log` incluindo `other` (em JSON)
- Índices internos: `timecreated`, `userid`, `courseid`, `eventname`, `relateduserid`
- Busca via SQL dentro do arquivo (sem tabela de índice separada no banco)
- Restauração: `sqlite_reader::get_contents_generator()` → `import_record()`
- Upload para external storage como `.db.gz` (gzip comprimido, nível 9)
- Para busca no S3: download do `.db.gz` → descomprime → abre como `.db` local

### CSV (`.csv`) — Archive-only

- Primeira linha = headers, demais = dados
- Campo `other` sempre em JSON
- **Não é buscável** — não aparece nos resultados do search service
- Restauração: `csv_reader::get_contents_generator()` → `import_record()`
- Download disponível pelo admin via `download.php`

### Indicação na UI

A lista de backups deve ter coluna **"Buscável"** (derivada do `fileformat`).

O search service, ao encontrar backups CSV no intervalo buscado, retorna contagem de quantos foram ignorados.

---

## 7. SQLite — Otimizações de Build

Aplicadas em sequência pelo `sqlite_writer`:

### Fase 1 — Antes do CREATE TABLE

```sql
PRAGMA page_size = 8192;
```

### Fase 2 — Durante os inserts

```sql
PRAGMA synchronous = OFF;
PRAGMA journal_mode = MEMORY;
PRAGMA temp_store = MEMORY;
PRAGMA cache_size = -65536;   -- 64 MB de cache em RAM
```

Todos os inserts dentro de **uma única transação**, com commit parcial a cada 10.000 registros.

### Fase 3 — Após todos os inserts (finalize)

```sql
COMMIT;
CREATE INDEX IF NOT EXISTS idx_timecreated   ON logs(timecreated);
CREATE INDEX IF NOT EXISTS idx_userid        ON logs(userid);
CREATE INDEX IF NOT EXISTS idx_courseid      ON logs(courseid);
CREATE INDEX IF NOT EXISTS idx_eventname     ON logs(eventname);
CREATE INDEX IF NOT EXISTS idx_relateduserid ON logs(relateduserid);
VACUUM;
PRAGMA journal_mode = DELETE;
PRAGMA synchronous = NORMAL;
```

Depois: `chmod($filepath, 0444)` — arquivo somente-leitura.

### Estimativa de tamanho (100k registros)

| Estado | Tamanho |
|--------|---------|
| SQLite sem otimizações | ~120 MB |
| + pragmas + transação | ~90 MB |
| + índices após inserts + VACUUM | ~65 MB |
| + gzip nível 9 (para external) | **~15–20 MB** |

---

## 8. SQLite — Leitura Somente

```php
$db = new \SQLite3(
    'file:' . $filepath . '?immutable=1',
    SQLITE3_OPEN_READONLY | SQLITE3_OPEN_URI
);
```

- Zero overhead de locking
- Não cria arquivos auxiliares (`.db-wal`, `.db-shm`)
- Impossível modificar por acidente

---

## 9. Migração Externa (external_migration_task)

### Tarefa: `external_migration_task`

- **Schedule:** Diário, 03:00 AM
- **Condição:** external service configurado e `external_migration_delay > 0`

A tarefa é **genérica** — seleciona o serviço adequado via `config::get_external_backup_service()`. Não é específica para S3.

### Lógica

```
1. Verificar se external service está configurado → sair se não
2. Verificar se external_migration_delay > 0 → sair se não

3. Buscar backups elegíveis:
   WHERE timecreated < (now - external_migration_delay)
     AND deleted = 0
     AND external_service IS NULL
     AND local_path IS NOT NULL

4. Para cada backup:
   a. Verificar se arquivo local existe (local_path)
   b. Chamar external_service::upload(backup) → retorna URI
   c. Atualizar backup: external_service, external_uri, external_customdata
   d. Se delete_local_after_external = true:
      → unlink(local_path)
      → backup: local_path = null
   e. Em caso de falha: mtrace() + continue (não lança exceção)
```

### Upload com compressão (SQLite)

```php
private static function gzip_file(string $input, string $output): void {
    $in  = fopen($input, 'rb');
    $out = gzopen($output, 'wb9');
    while (!feof($in)) {
        gzwrite($out, fread($in, 524288)); // chunks de 512 KB
    }
    fclose($in);
    gzclose($out);
}
```

### Interface `external_backup_service_interface`

```php
interface external_backup_service_interface {
    public static function is_enabled(): bool;
    public static function get_name(): string;

    // Returns s3://bucket/path/file.ext or similar URI
    public function upload(backup $backup): string;

    // Downloads to local $dest_path
    public function download_to_path(string $external_uri, string $dest_path): void;
}
```

O método `upload()` retorna a URI do arquivo no external storage.
O método `download_to_path()` baixa o arquivo para um path local.

O `external_migration_task` atualiza os campos `external_service`, `external_uri`, `external_customdata` no backup.

---

## 10. Busca — Síncrona e Assíncrona

### Retorno do search_service::search()

```php
[
    'results'       => [...],  // registros encontrados imediatamente
    'searched'      => [...],  // backups pesquisados com sucesso
    'skipped_csv'   => [...],  // backups CSV ignorados (não buscáveis)
    'pending'       => [...],  // backups sem local e sem cache → adhoc task criada
    'unavailable'   => [...],  // backups sem local e sem external storage
]
```

### Lógica para cada backup SQLite no intervalo

```
Para cada backup com fileformat = 'db':

  a. local_path existe e arquivo existe?
     → Abre com sqlite_reader imutável
     → Executa query SQL com filtros
     → Adiciona ao searched[]

  b. is_cached() = true?
     → touch(cache_path) para renovar TTL
     → Abre com sqlite_reader imutável
     → Executa query SQL com filtros
     → Adiciona ao searched[]

  c. has_external() = true, mas sem cache?
     → Verifica se já existe cache_download_task para este backup
     → Se não existe, cria cache_download_task (adhoc)
     → Adiciona ao pending[]

  d. Sem local e sem external?
     → Adiciona ao unavailable[]
```

### cache_download_task (adhoc)

```php
class cache_download_task extends adhoc_task {
    public function execute(): void {
        $data = $this->get_custom_data();
        $backup = new backup($data['backupid']);

        if ($backup->is_cached()) {
            return; // Already done by another process
        }

        $service = config::get_external_backup_service($backup->get('external_service'));
        if (!$service) {
            throw new \moodle_exception('noexternalservice', 'tool_stdlogarchiver');
        }

        $gz_tmp = $backup->get_cache_path() . '.gz.tmp';
        $service->download_to_path($backup->get('external_uri'), $gz_tmp);

        compression_helper::gunzip($gz_tmp, $backup->get_cache_path());
        @unlink($gz_tmp);
        chmod($backup->get_cache_path(), 0444);
    }

    public static function is_enqueued(int $backupid): bool { ... }
    public static function create_and_enqueue(int $backupid): void { ... }
}
```

### UI — Exibição de resultados pendentes

A UI de busca deve exibir:
- Resultados imediatos (de arquivos locais ou cacheados)
- Aviso de backups "pendentes de download" com lista de períodos
- Instrução: "Reexecute a busca em alguns minutos para ver os resultados completos"

---

## 11. Limpeza de Cache (cache_cleanup_task)

- **Schedule:** Diário, 04:00 AM
- Varre `config::get_cache_dir()`
- Deleta arquivos com `mtime < (now - cache_ttl)`
- Log de quantos arquivos foram deletados e espaço liberado

```php
public function execute(): void {
    $cache_dir = config::get_cache_dir();
    $ttl = config::get_cache_ttl();
    $cutoff = time() - $ttl;
    $deleted = 0;
    $freed = 0;

    foreach (glob($cache_dir . '/*.db') as $file) {
        if (filemtime($file) < $cutoff) {
            $freed += filesize($file);
            @unlink($file);
            $deleted++;
        }
    }

    mtrace("Cache cleanup: $deleted files deleted, " . display_size($freed) . " freed.");
}
```

---

## 12. Archive Task — Nova Lógica

### Algoritmo day-based

```
Loop (budget de 5 minutos):

  1. SELECT MIN(timecreated) FROM {logstore} WHERE timecreated < cutoff
     → Se null: EXIT (nada para arquivar)

  2. Compute UTC day boundaries:
     $day_start = floor($min_tc / 86400) * 86400
     $day_end   = $day_start + 86400

  3. $min_id = 0
     Loop:
       SELECT * WHERE timecreated ∈ [day_start, day_end) AND timecreated < cutoff AND id > $min_id
       ORDER BY id ASC LIMIT max_records_per_file

       Se vazio: próximo dia

       write_chunk($records)
       DELETE by explicit IDs (não BETWEEN)
       $min_id = max(IDs do chunk)
```

### write_chunk() — grava arquivo e cria registro no DB

```php
protected function write_chunk(string $table, array $records): void {
    global $DB;

    $first = reset($records);
    $last  = end($records);

    $format   = config::get_backup_format();
    $filename = sprintf('%d_%d.%s', (int)$first->timecreated, (int)$last->timecreated, $format);
    $filepath = config::get_backup_dir() . '/' . $filename;

    $writer_class = config::get_writer_class();
    $writer = new $writer_class($filepath);

    try {
        foreach ($records as $record) {
            $record->other = logstored_other_trait::to_json($record->other);
            $writer->append($record);
        }
        $writer->finalize();
    } catch (\Throwable $e) {
        $writer->destroy();
        throw $e;
    }

    $backup = new backup(0, (object)[
        'firstid'    => (int)$first->id,
        'lastid'     => (int)$last->id,
        'starttime'  => (int)$first->timecreated,
        'endtime'    => (int)$last->timecreated,
        'fileformat' => $format,
        'local_path' => $filepath,
    ]);
    $backup->save();

    // DELETE por IDs explícitos (não BETWEEN) — evita race condition
    $ids = array_column($records, 'id');
    foreach (array_chunk($ids, 1000) as $chunk) {
        [$in_sql, $in_params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'logid');
        $DB->delete_records_select($table, "id $in_sql", $in_params);
    }

    mtrace(sprintf('Archived %d records → %s (Backup #%d)', count($records), $filename, $backup->get('id')));
}
```

---

## 13. Configurações — Novas e Modificadas

### Novas configurações

| Chave | Tipo | Padrão | Descrição |
|-------|------|--------|-----------|
| `backup_dir` | Text | `''` (usa moodledata) | Path absoluto do diretório de backups |
| `cache_dir` | Text | `''` (usa sys_get_temp_dir) | Diretório de cache para arquivos externos |
| `cache_ttl` | Duration | `86400` (1 dia) | TTL de arquivos no cache |
| `external_migration_delay` | Duration | `0` (desabilitado) | Tempo para mover arquivo local para external storage |
| `delete_local_after_external` | Checkbox | `0` | Deletar arquivo local após upload externo |
| `max_records_per_file` | Select | `200000` | Cap de registros por arquivo |

### Configurações modificadas

| Chave | Mudança |
|-------|---------|
| `backup_format` | Opções: `db` (SQLite, padrão) e `csv` |
| `records_per_file` | **Removida** — substituída por `max_records_per_file` |
| `aws_secret` | Mudar para `admin_setting_configpasswordunmask` |
| `delete_local_after_external_backup` | **Renomeada** para `delete_local_after_external` |

### Configurações mantidas

`enabled`, `log_lifetime`, `external_backup_service`, `aws_region`, `aws_key`, `s3_bucket`, `s3_folder`

---

## 14. Estrutura de Classes

```
classes/
├── backup/
│   ├── external/
│   │   ├── external_backup_service_interface.php  (interface atualizada)
│   │   └── external_s3_backup_service.php         (adaptado)
│   ├── readers/
│   │   ├── reader_interface.php   (create(string $filepath))
│   │   ├── csv_reader.php         (modificado — filepath direto)
│   │   └── sqlite_reader.php      (novo — immutable + search())
│   ├── search/
│   │   └── search_service.php     (reescrito — SQL + async)
│   └── writers/
│       ├── writer_interface.php   (finalize() no lugar de to_stored_file())
│       ├── csv_writer.php         (modificado — filepath direto)
│       └── sqlite_writer.php      (novo — todas as otimizações)
├── config.php                      (atualizado — novos getters)
├── models/
│   └── backup.php                  (refatorado — schema novo, sem ext_backup model)
├── output/
│   └── ...                         (mínimas mudanças, adaptar tabelas para novo schema)
├── privacy/
│   └── provider.php                (sem mudanças)
├── restore/
│   └── backup_restorer.php         (sem mudanças — usa get_reader())
├── task/
│   ├── archive_task.php            (reescrito — day-based)
│   ├── cache_cleanup_task.php      (novo)
│   ├── cache_download_task.php     (novo — adhoc)
│   ├── external_migration_task.php (novo — genérico)
│   ├── restore_backup_task.php     (sem mudanças)
│   └── unrestore_backup_task.php   (sem mudanças)
└── util/
    ├── compression_helper.php      (novo — gzip/gunzip)
    ├── events_helper.php           (sem mudanças)
    ├── logstored_other_trait.php   (sem mudanças)
    ├── persistent_soft_delete_trait.php (sem mudanças)
    └── standard_logstore.php       (sem mudanças)

db/
├── install.php    (atualizado — defaults novos)
├── install.xml    (schema novo — tabela única, sem ext_bkps)
├── tasks.php      (tasks atualizadas)
└── upgrade.php    (novo)

download.php       (novo — serve arquivos diretamente)
lib.php            (remove pluginfile, mantém notify)
settings.php       (atualizado)
version.php        (bump para 2024120101, requires Moodle 4.2)
```

### `backup.php` — métodos do novo schema

| Método | Descrição |
|--------|-----------|
| `get_filename()` | `sprintf('%d_%d.%s', starttime, endtime, fileformat)` |
| `get_local_path()` | Retorna `local_path` do banco |
| `local_file_exists()` | `local_path` não nulo e arquivo existe |
| `has_external()` | `external_service` não nulo |
| `get_cache_path()` | Deriva do `external_uri` → `{cache_dir}/{filename}` |
| `is_cached()` | Cache existe e não expirou |
| `get_path_for_query()` | local_path → cache_path → lança exceção |
| `is_searchable()` | `fileformat === 'db'` |
| `delete_local_file()` | `unlink(local_path)` + seta `local_path = null` |
| `get_reader()` | Instancia reader da classe correta com `get_path_for_query()` |
| `undo_restore()` | DELETE from logstore (corrigido — usava tabela errada) |

### `external_backup_service_interface` — interface simplificada

```php
interface external_backup_service_interface {
    public static function is_enabled(): bool;
    public static function get_name(): string;

    // Returns the URI of the uploaded file (e.g. s3://bucket/path/file.db.gz)
    public function upload(backup $backup): string;

    // Downloads the file at $external_uri to local $dest_path
    public function download_to_path(string $external_uri, string $dest_path): void;
}
```

---

## 15. Tasks Agendadas

| Task | Tipo | Schedule | Descrição |
|------|------|----------|-----------|
| `archive_task` | Scheduled | Diário 01:00 AM | Arquiva logs → arquivos locais (com proteção de restauração) |
| `external_migration_task` | Scheduled | Diário 03:00 AM | Migra arquivos locais para external storage |
| `cache_cleanup_task` | Scheduled | Diário 04:00 AM | Limpa arquivos de cache expirados (por mtime) |
| `backup_purge_task` | Scheduled | Diário 05:00 AM | Apaga permanentemente arquivos (local + remoto) de backups deletados ou expirados |
| `restore_backup_task` | Adhoc | Sob demanda | Restaura backup para o logstore |
| `unrestore_backup_task` | Adhoc | Sob demanda | Desfaz restauração do logstore |
| `cache_download_task` | Adhoc | Sob demanda | Baixa arquivo remoto para cache (criado pelo search_service) |

### backup_purge_task

Critérios de purge (executados em ordem):

**1. Backups soft-deleted pelo usuário:**
```sql
WHERE deleted_at > 0
  AND (local_path IS NOT NULL OR external_service IS NOT NULL)
```
→ Deleta arquivos, anula local_path/external_service/external_uri

**2. Auto-purge por TTL (se `backup_retention_ttl > 0`):**
```sql
WHERE deleted_at = 0
  AND restored = 0
  AND timecreated < (NOW() - backup_retention_ttl)
```
→ Soft-delete (deleted_at = now()) + deleta arquivos na mesma passagem

Para deletar do external storage, chama `service::delete(external_uri)`.

### external_backup_service_interface — método delete

```php
interface external_backup_service_interface {
    public static function is_enabled(): bool;
    public static function get_name(): string;
    public function upload(backup $backup): string;           // retorna URI
    public function download_to_path(string $uri, string $dest): void;
    public function delete(string $external_uri): void;       // NOVO
}
```

---

## 16. O que Remover

| Arquivo/Componente | Motivo |
|--------------------|--------|
| `lib.php::tool_stdlogarchiver_pluginfile()` | Downloads agora via `download.php` |
| `classes/models/external/external_backup.php` | Substituído por colunas na tabela principal |
| `classes/task/external_backup_task.php` | Substituído por `external_migration_task` |
| Uso de `stored_file` em qualquer classe | Sem Moodle file storage |
| `/libs/` (AWS SDK) | Migrar para Composer |
| Coluna `pathnamehash` | Sem Moodle file storage |
| Config `records_per_file` | Substituída por `max_records_per_file` |
| Config `delete_local_after_external_backup` | Renomeada para `delete_local_after_external` |
| Tabela `tool_stdlogarchiver_ext_bkps` | Substituída por colunas na tabela principal |

---

## 17. Decisões Pendentes

| # | Questão | Opções |
|---|---------|--------|
| 1 | Cap de registros por arquivo | 100k / 200k / 500k — afeta tamanho e tempo de download |
| 2 | CSV vai para external storage com compressão? | `.csv` ou `.csv.gz` |
| 3 | Busca: paginação por backup ou por registro? | Por registro (SQL LIMIT/OFFSET agregado) é melhor UX |
| 4 | `.htaccess` no backup_dir: sempre criar ou só se sob docroot? | Sempre criar |
| 5 | Composer para AWS SDK: vendor no repo ou composer install no deploy? | Exigir composer install |
| 6 | Notificação ao admin após cache_download_task concluída? | Notificação Moodle ou apenas log |
| 7 | Cache cleanup: usar `glob()` ou `FilesystemIterator`? | `FilesystemIterator` para performance |

---

*Documento atualizado após revisão de arquitetura. Plugin nunca foi usado em produção — rewrite completo sem preocupação com migração de dados.*
