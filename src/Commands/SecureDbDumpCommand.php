<?php

namespace Webhub\SecureDbDump\Commands;

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

    public function handle(): int
    {
        $config = config('secure-db-dump');
        $this->currentConnection = $config['db_connection'] ?? DB::getDefaultConnection();
        $this->databaseConfig = config("database.connections.$this->currentConnection");

        $this->disk = $config['disk'] ?? 'local';

        $pathToDumpFile = $this->dumpOriginalDatabase($config);

        $this->setupTempDatabase();
        $this->importIntoTempDatabase($pathToDumpFile);

        $this->anonymizeDataInTempDatabase();

        //$this->dropDatabase();

        return self::SUCCESS;
    }

    private function dumpOriginalDatabase(mixed $config): string
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

        /*if ($config['only_content'] ?? false) {
            $dumper->doNotCreateTables();
        }*/

        if (!empty($config['ignore_tables'])) {
            $dumper->excludeTables($config['ignore_tables']);
        }

        $pathToDumpFile = Storage::disk($this->disk)->path('secure_dump_' . $this->databaseConfig['database'] . '_' . date('Ymd_His') . '.sql');
        $dumper->dumpToFile($pathToDumpFile);

        return $pathToDumpFile;
    }

    private function setupTempDatabase(): void
    {
        DB::statement('CREATE DATABASE IF NOT EXISTS '.$this->tempDatabaseName);

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
        try {
            DB::statement('DROP DATABASE IF EXISTS ' . $this->tempDatabaseName);
        } catch (\Exception $exception)
        {
            $this->error('Failed to drop database: ' . $exception->getMessage());
            $this->info('Trying to drop all tables instead.');
            // TODO...
        }
    }
}
