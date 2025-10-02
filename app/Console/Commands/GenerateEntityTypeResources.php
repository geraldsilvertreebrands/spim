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

        // Generate the Pages directory and files
        $this->generatePages($entityType, $className);

        $this->info("  Generated {$className}");
    }

    protected function generatePages(EntityType $entityType, string $resourceClass): void
    {
        $directory = app_path("Filament/Resources/{$resourceClass}");
        $pagesDirectory = "{$directory}/Pages";

        File::ensureDirectoryExists($pagesDirectory);

        // List page
        $listPageClass = "List" . Str::plural(Str::studly($entityType->name));
        $listPagePath = "{$pagesDirectory}/{$listPageClass}.php";

        if (!File::exists($listPagePath)) {
            File::put($listPagePath, $this->getListPageStub($entityType, $resourceClass, $listPageClass));
        }
    }

    protected function getResourceStub(EntityType $entityType, string $className, string $namespace): string
    {
        $entityTypeName = $entityType->name;
        $entityTypeId = $entityType->id;
        $studlyName = Str::studly($entityType->name);
        $pluralStudly = Str::plural($studlyName);
        $listPageClass = "List" . $pluralStudly;

        return <<<PHP
<?php

namespace {$namespace};

use App\Filament\Resources\\{$className}\\Pages;
use App\Models\Attribute;
use App\Models\Entity;
use App\Models\UserPreference;
use App\Support\AttributeUiRegistry;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class {$className} extends Resource
{
    protected static ?string \$model = Entity::class;

    protected static ?string \$navigationGroup = 'Entities';

    protected static ?string \$navigationIcon = 'heroicon-o-cube';

    protected static ?string \$navigationLabel = '{$entityTypeName}';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('entity_type_id', {$entityTypeId});
    }

    public static function table(Table \$table): Table
    {
        return \$table
            ->columns(static::getTableColumns())
            ->filters([])
            ->actions([
                Tables\\Actions\\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (Entity \$record): string => "{$entityTypeName}: {\$record->id}")
                    ->modalContent(fn (Entity \$record) => view('filament.components.entity-detail-modal', [
                        'entity' => \$record,
                        'entityType' => \$record->entityType,
                    ]))
                    ->modalWidth('7xl')
                    ->slideOver(),
            ])
            ->bulkActions([
                Tables\\Actions\\BulkActionGroup::make([
                    Tables\\Actions\\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }

    protected static function getTableColumns(): array
    {
        \$columns = [
            Tables\\Columns\\TextColumn::make('id')
                ->label('ID')
                ->searchable()
                ->sortable(),
        ];

        \$entityTypeId = {$entityTypeId};
        \$preferenceKey = "entity_type_{\$entityTypeId}_columns";
        \$userId = Auth::id();

        \$selectedAttributes = \$userId
            ? UserPreference::get(\$userId, \$preferenceKey, static::getDefaultColumns())
            : static::getDefaultColumns();

        \$attributes = Attribute::where('entity_type_id', \$entityTypeId)
            ->whereIn('name', \$selectedAttributes)
            ->get();

        \$registry = app(AttributeUiRegistry::class);

        foreach (\$attributes as \$attribute) {
            \$columns[] = Tables\\Columns\\TextColumn::make("attr_{\$attribute->name}")
                ->label(\$attribute->name)
                ->formatStateUsing(function (Entity \$record) use (\$attribute, \$registry) {
                    try {
                        \$ui = \$registry->resolve(\$attribute);
                        return \$ui->summarise(\$record, \$attribute);
                    } catch (\\Exception \$e) {
                        return \$record->getAttr(\$attribute->name) ?? '';
                    }
                })
                ->searchable(false);
        }

        return \$columns;
    }

    protected static function getDefaultColumns(): array
    {
        return Attribute::where('entity_type_id', {$entityTypeId})
            ->limit(5)
            ->pluck('name')
            ->toArray();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\\{$listPageClass}::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Disable create for now
    }
}

PHP;
    }

    protected function getListPageStub(EntityType $entityType, string $resourceClass, string $pageClass): string
    {
        return <<<PHP
<?php

namespace App\\Filament\\Resources\\{$resourceClass}\\Pages;

use App\\Filament\\Resources\\{$resourceClass};
use Filament\\Resources\\Pages\\ListRecords;

class {$pageClass} extends ListRecords
{
    protected static string \$resource = {$resourceClass}::class;
}

PHP;
    }
}

