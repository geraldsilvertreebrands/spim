<?php

namespace App\Console\Commands;

use App\Models\EntityType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerateEntityTypeResources extends Command
{
    protected $signature = 'entities:generate-resources';

    protected $description = 'Generate Filament resources for each entity type';

    public function handle(): int
    {
        $entityTypes = EntityType::all();

        if ($entityTypes->isEmpty()) {
            $this->warn('No entity types found in the database.');
            return self::FAILURE;
        }

        foreach ($entityTypes as $entityType) {
            $this->generateResource($entityType);
        }

        $this->info('Entity type resources generated successfully!');
        return self::SUCCESS;
    }

    protected function generateResource(EntityType $entityType): void
    {
        $className = Str::studly($entityType->name) . 'Resource';
        $namespace = 'App\\Filament\\Resources';
        $directory = app_path('Filament/Resources');
        $filePath = "{$directory}/{$className}.php";

        // Don't overwrite existing resources
        if (File::exists($filePath)) {
            $this->line("  Skipping {$className} (already exists)");
            return;
        }

        $stub = $this->getResourceStub($entityType, $className, $namespace);

        File::ensureDirectoryExists($directory);
        File::put($filePath, $stub);

        // Generate page classes
        $this->generatePages($entityType, $className);

        $this->info("  Generated {$className}");
    }

    protected function generatePages(EntityType $entityType, string $resourceClass): void
    {
        $directory = app_path("Filament/Resources/{$resourceClass}");
        $pagesDirectory = "{$directory}/Pages";

        File::ensureDirectoryExists($pagesDirectory);

        $pluralStudly = Str::plural(Str::studly($entityType->name));

        // List page
        $listPageClass = "List{$pluralStudly}";
        $listPagePath = "{$pagesDirectory}/{$listPageClass}.php";
        if (!File::exists($listPagePath)) {
            File::put($listPagePath, $this->getListPageStub($resourceClass, $listPageClass));
        }

        // Create page
        $createPageClass = "Create" . Str::studly($entityType->name);
        $createPagePath = "{$pagesDirectory}/{$createPageClass}.php";
        if (!File::exists($createPagePath)) {
            File::put($createPagePath, $this->getCreatePageStub($resourceClass, $createPageClass));
        }

        // Edit page
        $editPageClass = "Edit" . Str::studly($entityType->name);
        $editPagePath = "{$pagesDirectory}/{$editPageClass}.php";
        if (!File::exists($editPagePath)) {
            File::put($editPagePath, $this->getEditPageStub($resourceClass, $editPageClass));
        }
    }


    protected function getResourceStub(EntityType $entityType, string $className, string $namespace): string
    {
        $entityTypeName = $entityType->name;
        $pluralStudly = Str::plural(Str::studly($entityType->name));
        $listPageClass = "List{$pluralStudly}";
        $createPageClass = "Create" . Str::studly($entityType->name);
        $editPageClass = "Edit" . Str::studly($entityType->name);

        return <<<PHP
<?php

namespace {$namespace};

use App\Filament\Resources\AbstractEntityTypeResource;
use App\Filament\Resources\\{$className}\\Pages;

class {$className} extends AbstractEntityTypeResource
{
    public static function getEntityTypeName(): string
    {
        return '{$entityTypeName}';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\\{$listPageClass}::route('/'),
            'create' => Pages\\{$createPageClass}::route('/create'),
            'edit' => Pages\\{$editPageClass}::route('/{record}/edit'),
        ];
    }
}

PHP;
    }

    protected function getListPageStub(string $resourceClass, string $pageClass): string
    {
        return <<<PHP
<?php

namespace App\\Filament\\Resources\\{$resourceClass}\\Pages;

use App\\Filament\\Resources\\{$resourceClass};
use App\\Filament\\Resources\\Pages\\AbstractListEntityRecords;

class {$pageClass} extends AbstractListEntityRecords
{
    protected static string \$resource = {$resourceClass}::class;
}

PHP;
    }

    protected function getCreatePageStub(string $resourceClass, string $pageClass): string
    {
        return <<<PHP
<?php

namespace App\\Filament\\Resources\\{$resourceClass}\\Pages;

use App\\Filament\\Resources\\{$resourceClass};
use App\\Filament\\Resources\\Pages\\AbstractCreateEntityRecord;

class {$pageClass} extends AbstractCreateEntityRecord
{
    protected static string \$resource = {$resourceClass}::class;
}

PHP;
    }

    protected function getEditPageStub(string $resourceClass, string $pageClass): string
    {
        return <<<PHP
<?php

namespace App\\Filament\\Resources\\{$resourceClass}\\Pages;

use App\\Filament\\Resources\\{$resourceClass};
use App\\Filament\\Resources\\Pages\\AbstractEditEntityRecord;

class {$pageClass} extends AbstractEditEntityRecord
{
    protected static string \$resource = {$resourceClass}::class;
}

PHP;
    }
}


