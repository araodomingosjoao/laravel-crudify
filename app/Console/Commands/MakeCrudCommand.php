<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class MakeCrudCommand extends Command
{
    protected $signature = 'make:crud {name} {--fields=}';
    protected $description = 'Create CRUD operations for a model';

    public function handle()
    {
        $name = $this->argument('name');
        $fields = $this->option('fields');

        $this->createModel($name);
        $this->createMigration($name, $fields);
        $this->createController($name);
        $this->createViews($name);
        $this->info('CRUD for '.$name.' created successfully.');
    }

    protected function createModel($name)
    {
        Artisan::call('make:model', ['name' => $name, '-m' => true]);
    }

    protected function createMigration($name, $fields)
    {
        $tableName = Str::plural(Str::snake($name));
        $migrationName = 'create_' . $tableName . '_table';

        Artisan::call('make:migration', [
            'name' => $migrationName,
            '--create' => $tableName
        ]);

        $migrationPath = base_path('database/migrations/') . date('Y_m_d_His') . '_' . $migrationName . '.php';

        $fieldsArray = explode(',', $fields);
        $fieldsSchema = '';

        foreach ($fieldsArray as $field) {
            [$fieldName, $type] = explode(':', $field);
            $fieldsSchema .= "\$table->$type('$fieldName');\n            ";
        }

        $migrationTemplate = file_get_contents(resource_path('stubs/migration.stub'));
        $migrationTemplate = str_replace(
            ['{{tableName}}', '{{fields}}'],
            [$tableName, $fieldsSchema],
            $migrationTemplate
        );

        file_put_contents($migrationPath, $migrationTemplate);
    }

    protected function createController($name)
    {
        Artisan::call('make:controller', ['name' => $name . 'Controller']);
    }

    protected function createViews($name)
    {
        
    }
}
