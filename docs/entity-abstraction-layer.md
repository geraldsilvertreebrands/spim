# Entity Abstraction Layer

## Overview

The entity browsing system is now fully abstracted, making it easy to create new entity types with minimal code duplication.

## Architecture

### Service Layer

**`App\Services\EntityFormBuilder`**
- Dynamically generates form components based on attributes
- Handles all data types (text, integer, html, select, belongs_to, etc.)
- Reusable across all entity types
- ~100 lines of code serves all entity types

**`App\Services\EntityTableBuilder`**
- Generates table columns based on attributes
- Integrates with AttributeUiRegistry for rendering
- Manages user preferences for column selection
- Provides sensible defaults

### Resource Layer

**`App\Filament\Resources\AbstractEntityTypeResource`**
- Base class for all entity type resources
- Handles query scoping by entity_type_id
- Generates forms and tables using service layer
- Provides default actions (view, delete)
- Can be overridden for customization

**Concrete Resources** (e.g., `ProductEntityResource`)
```php
class ProductEntityResource extends AbstractEntityTypeResource
{
    public static function getEntityTypeName(): string
    {
        return 'Product';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
```
**That's it! 10-15 lines per entity type.**

### Page Layer

**Abstract Pages:**
- `AbstractListEntityRecords` - Adds create button
- `AbstractCreateEntityRecord` - Handles EAV attribute saving on create
- `AbstractEditEntityRecord` - Loads/saves EAV attributes on edit

**Concrete Pages** (e.g., `ProductEntityResource/Pages/ListProducts.php`)
```php
class ListProducts extends AbstractListEntityRecords
{
    protected static string $resource = ProductEntityResource::class;
}
```
**Minimal boilerplate, all logic in base class.**

## Creating a New Entity Type

### 1. Create Entity Type in Database
```php
EntityType::create([
    'name' => 'Brand',
    'description' => 'Product brands'
]);
```

### 2. Run Generator Command
```bash
php artisan entities:generate-resources
```

This creates:
- `app/Filament/Resources/BrandResource.php` (~15 lines)
- `app/Filament/Resources/BrandResource/Pages/ListBrands.php`
- `app/Filament/Resources/BrandResource/Pages/CreateBrand.php`
- `app/Filament/Resources/BrandResource/Pages/EditBrand.php`

### 3. Add Attributes
Use the Attributes admin to add attributes for the entity type. They automatically appear in forms and tables.

## Customization Points

### Custom Form Layout
Override the `form()` method:
```php
public static function form(Schema $schema): Schema
{
    $builder = app(EntityFormBuilder::class);
    $components = $builder->buildComponents(static::getEntityType());
    
    // Wrap in sections, add custom fields, etc.
    return $schema->components([
        Forms\Components\Section::make('Basic Info')
            ->schema(array_slice($components, 0, 3)),
        Forms\Components\Section::make('Details')
            ->schema(array_slice($components, 3)),
    ]);
}
```

### Custom Table Columns
Override the `table()` method:
```php
public static function table(Table $table): Table
{
    $builder = app(EntityTableBuilder::class);
    $columns = $builder->buildColumns(static::getEntityType());
    
    // Add custom columns, modify defaults, etc.
    array_unshift($columns, 
        Tables\Columns\ImageColumn::make('image_url')
    );
    
    return $table->columns($columns)->filters([...]);
}
```

### Custom Actions
Override `getTableActions()` or `getBulkActions()`:
```php
protected static function getTableActions(): array
{
    return array_merge(parent::getTableActions(), [
        Action::make('publish')
            ->icon('heroicon-o-check')
            ->action(fn (Entity $record) => $record->publish()),
    ]);
}
```

### Custom Validation
Override in page classes:
```php
class CreateProduct extends AbstractCreateEntityRecord
{
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Custom validation
        if ($data['price'] < 0) {
            throw new \Exception('Price cannot be negative');
        }
        
        return parent::mutateFormDataBeforeCreate($data);
    }
}
```

### Custom Icon
Simply set in the resource:
```php
class BrandResource extends AbstractEntityTypeResource
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;
    
    // ...
}
```

## Benefits

### Code Reduction
- **Before**: ~300 lines per entity type
- **After**: ~15 lines per entity type (98% reduction)
- Core logic centralized in 4 classes

### Maintainability
- Fix bugs once, benefits all entity types
- Add features globally with ease
- Clear separation of concerns

### Flexibility
- Easy to customize specific entity types
- Can override any method
- Service layer is reusable

### Consistency
- All entity types work the same way
- Same UX across the board
- Predictable behavior

## File Structure

```
app/
├── Services/
│   ├── EntityFormBuilder.php         # Form generation logic
│   └── EntityTableBuilder.php        # Table generation logic
│
├── Filament/
│   └── Resources/
│       ├── AbstractEntityTypeResource.php    # Base resource
│       ├── ProductEntityResource.php         # 15 lines
│       ├── CategoryEntityResource.php        # 15 lines
│       │
│       ├── Pages/
│       │   ├── AbstractListEntityRecords.php
│       │   ├── AbstractCreateEntityRecord.php
│       │   └── AbstractEditEntityRecord.php
│       │
│       ├── ProductEntityResource/
│       │   └── Pages/
│       │       ├── ListProducts.php          # 5 lines
│       │       ├── CreateProduct.php         # 5 lines
│       │       └── EditProduct.php           # 5 lines
│       │
│       └── CategoryEntityResource/
│           └── Pages/
│               ├── ListCategories.php
│               ├── CreateCategories.php
│               └── EditCategories.php
│
└── Console/
    └── Commands/
        └── GenerateEntityTypeResources.php   # Auto-generate resources
```

## Testing

The abstraction layer works with existing tests:
- `tests/Feature/EntityBrowsingTest.php` - Tests EAV operations
- `tests/Unit/AttributeServiceTest.php` - Tests attribute validation

Add new tests for specific entity types as needed.

## Migration from Old Code

If you have existing entity-specific code:
1. Keep the resource class
2. Change it to extend `AbstractEntityTypeResource`
3. Remove duplicated logic (form, table, pages)
4. Keep only customizations
5. Delete old page files if using abstract pages

## Future Enhancements

Potential additions to the abstraction layer:
- **Column chooser UI** - Let users pick which attributes to show
- **Saved filters** - Save common filter combinations
- **Bulk operations** - Custom bulk actions per entity type
- **Import/Export** - Generic CSV import/export for any entity type
- **Activity log** - Track changes to entities
- **Custom widgets** - Dashboard widgets per entity type

## Summary

The abstraction layer provides:
- ✅ **90%+ code reduction** for new entity types
- ✅ **Complete flexibility** for customization
- ✅ **Centralized maintenance** of core logic
- ✅ **Consistent UX** across all entity types
- ✅ **Easy to extend** with new features

New entity type = `php artisan entities:generate-resources` + done! 🎉

