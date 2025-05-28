<?php

namespace Webhub\SecureDbDump\Commands;

use Exception;
use Faker\Factory;
use Faker\Generator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\DbDumper\Compressors\GzipCompressor;
use Spatie\DbDumper\Databases\MariaDb;
use Spatie\DbDumper\Databases\MongoDb;
use Spatie\DbDumper\Databases\MySql;
use Spatie\DbDumper\Databases\PostgreSql;
use Spatie\DbDumper\Databases\Sqlite;

class SecureDbDumpCommand extends Command
{
    public $signature = 'secure-db-dump:run';

    public $description = 'My command';

    private string $originalDatabaseConnection;

    private array $originalDatabaseConfig;

    private array $tempDatabaseConfig;

    private string $tempDatabaseName = 'temp_secure_db_dump';

    private string $pathToOriginalDumpFile;

    private string $pathToSecureDumpFile;

    private Generator $faker;

    public function handle(): int
    {
        $this->faker = Factory::create(config('secure-db-dump.faker_locale', 'de_DE'));
        $this->originalDatabaseConnection = config('secure-db-dump.db_connection') ?? DB::getDefaultConnection();
        $this->originalDatabaseConfig = config("database.connections.$this->originalDatabaseConnection");
        $dumpFileName = $this->originalDatabaseConfig['database'].'_'.date('Ymd_His').'.sql.gz';
        $this->pathToOriginalDumpFile = Storage::disk(config('secure-db-dump.disk') ?? 'local')->path('original_dump_'.$dumpFileName);
        $this->pathToSecureDumpFile = Storage::disk(config('secure-db-dump.disk') ?? 'local')->path('secure_dump_'.$dumpFileName);

        $this->dumpOriginalDatabase();

        $this->setupTempDatabase();
        $this->importIntoTempDatabase();
        $this->anonymizeDataInTempDatabase();
        $this->dumpSecureDatabase();

        $this->cleanUpTempDatabase();

        if ($this->ask('Should I delete the original dump file? (yes/no)', 'yes') === 'yes') {
            unlink($this->pathToOriginalDumpFile);
        }

        $this->info('You can find your secure database dump at: '.$this->pathToSecureDumpFile);

        return self::SUCCESS;
    }

    private function dumpOriginalDatabase(): void
    {
        $dumper = match ($this->originalDatabaseConfig['driver']) {
            'mysql' => new MySql,
            'mariadb' => new MariaDb,
            'pgsql' => new PostgreSql,
            'sqlite' => new Sqlite,
            'mongodb' => new MongoDb,
            default => throw new \Exception('Unsupported database connection: '.$this->originalDatabaseConnection),
        };

        $dumper = $dumper::create()
            ->setDbName($this->originalDatabaseConfig['database'])
            ->setUserName($this->originalDatabaseConfig['username'])
            ->setPassword($this->originalDatabaseConfig['password'])
            ->useCompressor(new GzipCompressor());

        $this->info('Dumping original database to '.$this->pathToOriginalDumpFile.'...');
        $dumper->dumpToFile($this->pathToOriginalDumpFile);
        $this->info('✅ Done.');
    }

    private function dumpSecureDatabase(): void
    {
        $dumper = match ($this->tempDatabaseConfig['driver']) {
            'mysql' => new MySql,
            'mariadb' => new MariaDb,
            'pgsql' => new PostgreSql,
            'sqlite' => new Sqlite,
            'mongodb' => new MongoDb,
            default => throw new \Exception('Unsupported database connection: '.$this->originalDatabaseConnection),
        };

        $dumper = $dumper::create()
            ->setDbName($this->tempDatabaseConfig['database'])
            ->setUserName($this->tempDatabaseConfig['username'])
            ->setPassword($this->tempDatabaseConfig['password'])
            ->useCompressor(new GzipCompressor());

        if (config('secure-db-dump.only_content') ?? false) {
            $dumper->doNotCreateTables();
        }

        $this->info('Dumping secure database to '.$this->pathToSecureDumpFile.'...');
        $dumper->dumpToFile($this->pathToSecureDumpFile);
        $this->info('✅ Done.');
    }

    private function setupTempDatabase(): void
    {
        $this->info('Setting up temporary database...');
        DB::statement('CREATE DATABASE IF NOT EXISTS '.$this->tempDatabaseName);

        config(['database.connections.temp_secure_db_dump' => [
            'driver' => 'mysql',
            'host' => $this->originalDatabaseConfig['host'],
            'port' => $this->originalDatabaseConfig['port'],
            'database' => $this->tempDatabaseName,
            'username' => $this->originalDatabaseConfig['username'],
            'password' => $this->originalDatabaseConfig['password'],
            // ... other settings
        ]]);

        DB::setDefaultConnection('temp_secure_db_dump');
        $this->tempDatabaseConfig = config('database.connections.temp_secure_db_dump');
        $this->info('✅ Done.');
    }

    private function importIntoTempDatabase(): void
    {
        $this->info('Importing the original dump into the temporary database...');
        //DB::unprepared(file_get_contents($this->pathToOriginalDumpFile));
        exec("gunzip -c {$this->pathToOriginalDumpFile} | mysql -u{$this->tempDatabaseConfig['username']} -p{$this->tempDatabaseConfig['password']} {$this->tempDatabaseConfig['database']}", $output, $returnVar);
        if ($returnVar !== 0) {
            throw new Exception("Error importing database: " . implode("\n", $output));
        }

        if (! empty(config('secure-db-dump.ignore_tables'))) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            foreach (config('secure-db-dump.ignore_tables') as $table) {
                $this->info('Truncating ignored table: '.$table);
                DB::table($table)->truncate();
            }
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
        $this->info('✅ Done.');
    }

    private function cleanUpTempDatabase(): void
    {
        $this->info('Cleaning up the temporary database...');
        if (app()->isLocal()) {
            $this->warn('Skipping dropping database in local environment.');

            return;
        }

        try {
            DB::statement('DROP DATABASE IF EXISTS '.$this->tempDatabaseName);
        } catch (\Exception $exception) {
            $this->error('Failed to drop database: '.$exception->getMessage());

            $this->info('Trying to drop all tables instead.');
            $tables = DB::select('SHOW TABLES');
            foreach ($tables as $table) {
                $tableName = array_values((array) $table)[0];
                DB::statement("DROP TABLE IF EXISTS `$tableName`");
            }
        }
        $this->info('✅ Done.');
    }

    private function anonymizeDataInTempDatabase(): void
    {
        $this->info('Anonymizing the temporary database...');
        $fieldsToAnonymizeGroupedByTable = config('secure-db-dump.anonymize_fields', []);

        collect($fieldsToAnonymizeGroupedByTable)->each(function ($fields, $table) {
            $this->info('Anonymizing fields in table: '.$table);
            $this->withProgressBar(DB::table($table)->cursor(), function ($row) use ($fields, $table) {
                $dataToUpdate = [];
                foreach ($fields as $config) {
                    $field = $config['field'] ?? null;
                    $type = $config['type'] ?? null;
                    $whereConditions = $config['where'] ?? null;

                    if($field === null || $type === null || empty($row->$field)){
                        continue;
                    }

                    if($whereConditions !== null){
                        $matches = true;
                        foreach ($whereConditions as $whereField => $whereValue) {
                            if ($row->$whereField !== $whereValue) {
                                $matches = false;
                                break;
                            }
                        }
                        if (!$matches) {
                            continue;
                        }
                    }

                    if ($type === 'faker') {
                        $method = $config['method'];
                        $args = $config['args'] ?? [];
                        $dataToUpdate[$field] = $this->faker->$method(...$args);

                        continue;
                    }

                    if ($type === 'static') {
                        $dataToUpdate[$field] = $config['value'];
                    }
                }
                DB::table($table)->where('id', $row->id)->update($dataToUpdate);
            });
            $this->info('');
        });
        $this->info('✅ Done.');
    }
}
