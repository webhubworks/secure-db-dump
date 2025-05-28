<?php

namespace Webhub\SecureDbDump\Commands;

use Faker\Factory;
use Faker\Generator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\DbDumper\Databases\MariaDb;
use Spatie\DbDumper\Databases\MongoDb;
use Spatie\DbDumper\Databases\MySql;
use Spatie\DbDumper\Databases\PostgreSql;
use Spatie\DbDumper\Databases\Sqlite;

class SecureDbDumpCommand extends Command
{
    public $signature = 'secure-db-dump:run';

    public $description = 'My command';

    private string $currentConnection;
    private array $databaseConfig;
    private string $tempDatabaseName = 'temp_secure_db_dump';
    private string $disk;

    private Generator $faker;

    public function handle(): int
    {
        $this->faker = Factory::create();
        $this->currentConnection = config('secure-db-dump.db_connection') ?? DB::getDefaultConnection();
        $this->databaseConfig = config("database.connections.$this->currentConnection");

        $this->disk = config('secure-db-dump.disk') ?? 'local';

        $pathToDumpFile = $this->dumpOriginalDatabase();

        $this->setupTempDatabase();
        $this->importIntoTempDatabase($pathToDumpFile);

        $this->anonymizeDataInTempDatabase();

        $this->dropDatabase();
        unlink($pathToDumpFile);

        return self::SUCCESS;
    }

    private function dumpOriginalDatabase(): string
    {
        $dumper = match ($this->currentConnection) {
            'mysql' => new MySql,
            'mariadb' => new MariaDb,
            'pgsql' => new PostgreSql,
            'sqlite' => new Sqlite,
            'mongodb' => new MongoDb,
            default => throw new \Exception('Unsupported database connection: ' . $this->currentConnection),
        };

        $dumper = $dumper::create()
            ->setDbName($this->databaseConfig['database'])
            ->setUserName($this->databaseConfig['username'])
            ->setPassword($this->databaseConfig['password']);

        /*if (config('secure-db-dump.only_content') ?? false) {
            $dumper->doNotCreateTables();
        }*/

        if (!empty(config('secure-db-dump.ignore_tables'))) {
            $dumper->excludeTables(config('secure-db-dump.ignore_tables'));
        }

        $pathToDumpFile = Storage::disk($this->disk)->path('secure_dump_' . $this->databaseConfig['database'] . '_' . date('Ymd_His') . '.sql');
        $dumper->dumpToFile($pathToDumpFile);

        return $pathToDumpFile;
    }

    private function setupTempDatabase(): void
    {
        DB::statement('CREATE DATABASE IF NOT EXISTS ' . $this->tempDatabaseName);

        config(['database.connections.temp_secure_db_dump' => [
            'driver' => 'mysql',
            'host' => $this->databaseConfig['host'],
            'port' => $this->databaseConfig['port'],
            'database' => $this->tempDatabaseName,
            'username' => $this->databaseConfig['username'],
            'password' => $this->databaseConfig['password'],
            // ... other settings
        ]]);

        DB::setDefaultConnection('temp_secure_db_dump');
    }

    private function importIntoTempDatabase(string $pathToDumpFile): void
    {
        DB::unprepared(file_get_contents($pathToDumpFile));
    }

    private function dropDatabase(): void
    {
        if(app()->isLocal()) {
            $this->info('Skipping dropping database in local environment.');
            return;
        }

        try {
            DB::statement('DROP DATABASE IF EXISTS ' . $this->tempDatabaseName);
        } catch (\Exception $exception) {
            $this->error('Failed to drop database: ' . $exception->getMessage());

            $this->info('Trying to drop all tables instead.');
            $tables = DB::select('SHOW TABLES');
            foreach ($tables as $table) {
                $tableName = array_values((array)$table)[0];
                DB::statement("DROP TABLE IF EXISTS `$tableName`");
            }
        }
    }

    private function anonymizeDataInTempDatabase(): void
    {
        $fieldsToAnonymizeGroupedByTable = config('secure-db-dump.anonymize_fields', []);

        collect($fieldsToAnonymizeGroupedByTable)->each(function ($fields, $table) {
            $this->info('Anonymizing fields in table: ' . $table);
            $this->withProgressBar(DB::table($table)->cursor(), function ($row) use ($fields, $table) {
                $dataToUpdate = [];
                foreach ($fields as $field => $config) {

                    if ($config['type'] === 'faker') {
                        $method = $config['method'];
                        $dataToUpdate[$field] = $this->faker->$method($config['value'] ?? null);
                        continue;
                    }

                    if ($config['type'] === 'static') {
                        $dataToUpdate[$field] = $config['value'];
                    }
                }
                DB::table($table)->where('id', $row->id)->update($dataToUpdate);
            });
            $this->info('');
        });
    }
}
