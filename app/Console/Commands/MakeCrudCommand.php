<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class MakeCrudCommand extends Command
{
    protected $signature = 'make:crud
                            {name}
                            {--fields=}
                            {--relations=}
                            {--creation-rules=}
                            {--update-rules=}';
                            
    protected $description = 'Create CRUD operations for a model, including migrations, controllers, and views.';

    public function handle()
    {
        $name = $this->argument('name');
        $fields = $this->option('fields');
        $relations = $this->option('relations');

        $this->createModel($name, $fields, $relations);
        $this->createMigration($name, $fields, $relations);
        $this->createController($name);
        $this->createRequests($name, $fields);
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

    protected function createMigration($name, $fields, $relations)
    {
        Artisan::call('make:migration', ['name' => "create_".Str::plural(Str::snake($name))."_table"]);

        $migrationPath = base_path('database/migrations/');
        $migrationFile = $this->getLatestMigrationFile($migrationPath, "create_".Str::plural(Str::snake($name))."_table");

        $migrationTemplate = file_get_contents(resource_path('stubs/migration.stub'));
        $migrationTemplate = str_replace(
            ['{{tableName}}', '{{fields}}'],
            [Str::plural(Str::snake($name)), $this->getMigrationFields($fields)],
            $migrationTemplate
        );

        file_put_contents($migrationPath.'/'.$migrationFile, $migrationTemplate);

        $this->createPivotTables($relations);
    }

    protected function getMigrationFields($fields)
    {
        $fieldsArray = explode(',', $fields);
        $migrationFields = [];

        foreach ($fieldsArray as $field) {
            [$fieldName, $fieldType] = explode(':', $field);
            $migrationFields[] = "\$table->$fieldType('$fieldName');";
        }

        return implode("\n\t\t\t", $migrationFields);
    }

    protected function createPivotTables($relations)
    {
        if (!$relations) return;

        $relationsArray = explode(',', $relations);

        foreach ($relationsArray as $relation) {
            [$type, $relatedModel, $foreignKey, $localKey] = explode(':', $relation);

            if ($type === 'belongsToMany') {
                $pivotTableName = $this->getPivotTableName($relatedModel, $foreignKey);
                Artisan::call('make:migration', ['name' => "create_{$pivotTableName}_table"]);

                $migrationPath = base_path('database/migrations/');
                $migrationFile = $this->getLatestMigrationFile($migrationPath, "create_{$pivotTableName}_table");

                $pivotMigrationTemplate = file_get_contents(resource_path('stubs/pivot_migration.stub'));
                $pivotMigrationTemplate = str_replace(
                    ['{{pivotTableName}}', '{{foreignKey}}', '{{relatedKey}}'],
                    [$pivotTableName, $foreignKey, $localKey],
                    $pivotMigrationTemplate
                );

                file_put_contents($migrationPath.'/'.$migrationFile, $pivotMigrationTemplate);
            }
        }
    }

    protected function getPivotTableName($relatedModel, $modelName)
    {
        $model = Str::snake(Str::singular(class_basename($modelName)));
        $related = Str::snake(Str::singular(class_basename($relatedModel)));

        return collect([$model, $related])->sort()->implode('_');
    }

    protected function getLatestMigrationFile($migrationPath, $fileName)
    {
        $files = scandir($migrationPath, SCANDIR_SORT_DESCENDING);
        foreach ($files as $file) {
            if (str_contains($file, $fileName)) {
                return $file;
            }
        }
        return null;
    }

    protected function createController($name)
    {
        $controllerName = $name . 'Controller';
        $storeRequestName = $name . 'StoreRequest';
        $updateRequestName = $name . 'UpdateRequest';

        Artisan::call('make:controller', ['name' => $controllerName]);

        $controllerPath = app_path("Http/Controllers/{$controllerName}.php");
        $controllerTemplate = file_get_contents(resource_path('stubs/controller.stub'));

        $controllerTemplate = str_replace(
            ['{{controllerName}}', '{{modelName}}', '{{modelVariable}}', '{{storeRequestName}}', '{{updateRequestName}}'],
            [$controllerName, $name, strtolower($name), $storeRequestName, $updateRequestName],
            $controllerTemplate
        );

        file_put_contents($controllerPath, $controllerTemplate);
    }

    protected function createRequests($name, $fields)
    {
        $storeRules = $this->generateValidationRules($fields, true);
        $updateRules = $this->generateValidationRules($fields, false);

        $this->createRequest('StoreRequest', $storeRules, $name);
        $this->createRequest('UpdateRequest', $updateRules, $name);
    }

    protected function generateValidationRules($fields, $isCreation)
    {
        $rules = [];
        $fieldsArray = explode(',', $fields);

        foreach ($fieldsArray as $field) {
            [$fieldName, $fieldType] = explode(':', $field);
            $rules[$fieldName] = $this->getFieldRules($fieldType, $isCreation);
        }

        return $rules;
    }

    protected function getFieldRules($fieldType, $isCreation)
    {
        $rules = $isCreation ? ['required', $fieldType] : ['nullable', $fieldType];
    
        return implode('|', $rules);
    }
    
    protected function createRequest($stubName, $rules, $modelName)
    {
        $requestPath = app_path("Http/Requests/{$modelName}{$stubName}.php");
        $stubPath = resource_path("stubs/{$stubName}.stub");

        if (!file_exists($stubPath)) {
            $this->error("Stub file not found: {$stubPath}");
            return;
        }

        $requestTemplate = file_get_contents($stubPath);

        $rulesFormatted = collect($rules)->map(function ($rule, $field) {
            return "'{$field}' => '{$rule}'";
        })->implode(",\n            ");

        $requestTemplate = str_replace(
            ['{{formRequestName}}', '{{rules}}'],
            ["{$modelName}{$stubName}", $rulesFormatted],
            $requestTemplate
        );

        file_put_contents($requestPath, $requestTemplate);
    }

    protected function createViews($name)
    {
        
    }
}
