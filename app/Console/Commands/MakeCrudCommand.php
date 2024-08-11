<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class MakeCrudCommand extends Command
{
    protected $signature = 'make:crud {name} {--fields=} {--relations=}';
    protected $description = 'Create CRUD operations for a model, including migrations, controllers, and views.';

    public function handle()
    {
        $name = $this->argument('name');
        $fields = $this->option('fields');

        $relations = $this->option('relations');

        $this->createModel($name, $fields, $relations);
        $this->createMigration($name, $fields);
        $this->createController($name);
        $this->createViews($name);
        $this->info('CRUD for ' . $name . ' created successfully.');
    }

    protected function createModel($name, $fields, $relations)
    {
        Artisan::call('make:model', ['name' => $name]);

        $modelPath = app_path("Models/{$name}.php");
        $tableName = Str::plural(Str::snake($name));
        $fillableFields = $this->getFillableFields($fields);
        $relationMethods = $this->getRelationMethods($relations);

        $modelTemplate = file_get_contents(resource_path('stubs/model.stub'));
        $modelTemplate = str_replace(
            ['{{modelName}}', '{{tableName}}', '{{fillable}}', '{{relations}}'],
            [$name, $tableName, $fillableFields, $relationMethods],
            $modelTemplate
        );

        file_put_contents($modelPath, $modelTemplate);
    }

    protected function getFillableFields($fields)
    {
        $fieldsArray = explode(',', $fields);
        $fillableArray = [];

        foreach ($fieldsArray as $field) {
            [$fieldName] = explode(':', $field);
            $fillableArray[] = "'$fieldName'";
        }

        return implode(', ', $fillableArray);
    }

    protected function getRelationMethods($relations)
    {
        if (!$relations) return '';

        $relationMethods = '';
        $relationsArray = explode(',', $relations);

        foreach ($relationsArray as $relation) {
            [$type, $relatedModel, $foreignKey, $localKey] = explode(':', $relation);

            switch ($type) {
                case 'hasMany':
                    $relationMethods .= $this->createHasManyMethod($relatedModel, $foreignKey, $localKey);
                    break;
                case 'belongsTo':
                    $relationMethods .= $this->createBelongsToMethod($relatedModel, $foreignKey, $localKey);
                    break;
                case 'belongsToMany':
                    $relationMethods .= $this->createBelongsToManyMethod($relatedModel, $foreignKey, $localKey);
                    break;
                case 'hasOne':
                    $relationMethods .= $this->createHasOneMethod($relatedModel, $foreignKey, $localKey);
                    break;
                default:
                    break;
            }
        }

        return $relationMethods;
    }

    protected function createHasManyMethod($relatedModel, $foreignKey, $localKey)
    {
        return "
    public function " . Str::plural(Str::camel($relatedModel)) . "()
    {
        return \$this->hasMany($relatedModel::class, '$foreignKey', '$localKey');
    }\n";
    }

    protected function createBelongsToMethod($relatedModel, $foreignKey, $localKey)
    {
        return "
    public function " . Str::camel($relatedModel) . "()
    {
        return \$this->belongsTo($relatedModel::class, '$foreignKey', '$localKey');
    }\n";
    }

    protected function createBelongsToManyMethod($relatedModel, $foreignKey, $localKey)
    {
        return "
    public function " . Str::plural(Str::camel($relatedModel)) . "()
    {
        return \$this->belongsToMany($relatedModel::class, '$foreignKey', '$localKey');
    }\n";
    }

    protected function createHasOneMethod($relatedModel, $foreignKey, $localKey)
    {
        return "
    public function " . Str::camel($relatedModel) . "()
    {
        return \$this->hasOne($relatedModel::class, '$foreignKey', '$localKey');
    }\n";
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
        $controllerName = $name . 'Controller';
        Artisan::call('make:controller', ['name' => $controllerName]);

        $controllerPath = app_path("Http/Controllers/{$controllerName}.php");
        $modelVariable = strtolower($name);
        $modelName = $name;
        $controllerTemplate = file_get_contents(resource_path('stubs/controller.stub'));

        $controllerTemplate = str_replace(
            ['{{controllerName}}', '{{modelName}}', '{{modelVariable}}'],
            [$controllerName, $modelName, $modelVariable],
            $controllerTemplate
        );

        file_put_contents($controllerPath, $controllerTemplate);
    }

    protected function createViews($name)
    {
        
    }
}
