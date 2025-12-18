# Help Tooltips Reference

**Platform**: Silvertree Multi-Panel Platform
**Purpose**: Guide for adding help tooltips to the application
**Last Updated**: 2025-12-14

---

## Overview

The Silvertree platform uses Filament's built-in `helperText()` method to provide contextual help throughout the application. This document outlines where help tooltips exist and guidelines for adding more.

---

## Existing Help Tooltips

### PIM Panel

**Attribute Resource** (`app/Filament/PimPanel/Resources/AttributeResource.php`):

- **Display Name**: "Human-readable name shown in forms (e.g., Product Name). Leave empty to auto-generate from name."
- **Attribute Section**: "Optional: Group this attribute under a section"
- **Sort Order**: "Controls the display order within the section. Lower numbers appear first."
- **Data Type**: Describes each data type option
- **Validation Rules**: Explains each validation rule
- **Is Sync**: "If disabled, this attribute will not be synchronized with Magento"
- **Is Bidirectional Sync**: "If enabled, changes from both systems are merged. Conflicts require manual resolution."

**Attribute Section Resource** (`app/Filament/PimPanel/Resources/AttributeSectionResource.php`):

- **Sort Order**: "Sections with lower numbers appear first in forms"
- **Entity Type**: "Which entity type (e.g., Product, Category) this section applies to"

**Entity Type Resource** (`app/Filament/PimPanel/Resources/EntityTypeResource.php`):

- **Display Name**: "Human-friendly name shown in the UI"
- **Display Name Plural**: "Plural form for UI display"

**Pipeline Resources** (`app/Filament/PimPanel/Resources/PipelineResource/`):

- **Schedule**: "When this pipeline should run automatically"
- **Entity Filter**: "Optional: Only process entities matching this filter"
- **Module Configuration**: Help text for each pipeline module

**User Resource** (`app/Filament/PimPanel/Resources/UserResource.php`):

- **Is Active**: "Inactive users cannot log in"
- **Roles**: "Roles determine which panels the user can access"

**Magento Sync Page** (`app/Filament/PimPanel/Pages/MagentoSync.php`):

- Sync direction explanations
- Conflict resolution guidance

### Supply Panel

**Dashboard Pages**:
- Currently use inline help text within the views
- Located in `resources/views/filament/supply-panel/pages/`

**KPI Tooltips**:
- Hover tooltips on KPI tiles explaining metrics
- Trend arrows explained in-line

### Pricing Panel

**Dashboard Pages**:
- Inline help text in views
- Located in `resources/views/filament/pricing-panel/pages/`

**Price Alert Tooltips**:
- Alert type explanations
- Threshold guidance

---

## Adding New Help Tooltips

### For Filament Form Fields

Use the `helperText()` method:

```php
Forms\Components\TextInput::make('field_name')
    ->label('Field Label')
    ->helperText('Explanation of what this field does and how to use it')
```

**Guidelines:**

1. **Be concise**: 1-2 sentences max
2. **Be specific**: Give examples when helpful
3. **Be actionable**: Tell users what to do, not just what it is
4. **Use plain language**: Avoid jargon

**Examples:**

✅ **Good**: "Enter a unique product code (e.g., FTN-COC-500). Required."

❌ **Bad**: "SKU field for product identification purposes"

### For Blade Views

Use Blade directives with tooltip components:

```blade
<x-filament::badge
    tooltip="Explanation text here"
    icon="heroicon-o-information-circle"
>
    Label
</x-filament::badge>
```

Or use Alpine.js tooltips:

```blade
<span
    x-data="{ tooltip: 'Explanation text' }"
    x-tooltip="tooltip"
    class="cursor-help"
>
    Hover for info
</span>
```

### For Custom Livewire Components

Add public properties and render in view:

```php
public string $helpText = 'Explanation text';
```

```blade
@if($helpText)
    <p class="text-sm text-gray-500">{{ $helpText }}</p>
@endif
```

---

## Best Practices

### When to Add Tooltips

Add tooltips for:

✅ Complex or technical fields
✅ Fields with validation rules
✅ Fields that affect other parts of the system
✅ Optional vs required fields
✅ Format requirements (dates, SKUs, etc.)
✅ Fields with specific business logic

**Don't** add tooltips for:

❌ Self-explanatory fields (e.g., "Name", "Email")
❌ Fields with clear labels and obvious purpose
❌ Every single field (causes tooltip fatigue)

### Writing Good Help Text

**Template**: "[What it does]. [How to use it]. [Example if helpful]."

**Examples:**

- "Controls the display order. Lower numbers appear first. Use multiples of 10 (10, 20, 30) to allow reordering."
- "Select the parent category for hierarchical organization. Leave blank for top-level categories."
- "Enter product cost in ZAR. Used for margin calculations in Pricing panel."

### Accessibility

- Use semantic HTML
- Ensure tooltips are keyboard accessible
- Don't rely solely on tooltips for critical information
- Provide alternative text for screen readers

---

## Tooltip Coverage by Area

| Area | Coverage | Notes |
|------|----------|-------|
| PIM Attributes | ✅ Excellent | All fields documented |
| PIM Attribute Sections | ✅ Good | Key fields documented |
| PIM Entity Types | ✅ Good | Core fields documented |
| PIM Pipelines | ✅ Excellent | Complex workflows explained |
| PIM Users | ✅ Good | Role system explained |
| PIM Magento Sync | ⚠️ Moderate | Could add more inline help |
| Supply Dashboard | ⚠️ Moderate | KPI tooltips present, could expand |
| Supply Premium Pages | ⚠️ Moderate | Some inline help, could add more |
| Pricing Dashboard | ⚠️ Moderate | Basic help present |
| Pricing Alerts | ✅ Good | Alert types explained |

---

## Areas for Improvement

### Priority 1 (High Value)

1. **Supply Panel - Forecasting Page**: Add tooltips explaining confidence intervals, forecast methodology
2. **Supply Panel - RFM Analysis**: Explain RFM scores (1-5 scale)
3. **Pricing Panel - Margin Analysis**: Explain margin calculations, color coding
4. **PIM - Side-by-Side Edit**: Explain overrideable vs synced fields

### Priority 2 (Medium Value)

1. **Supply Panel - Cohort Page**: Explain cohort retention metrics
2. **Pricing Panel - Price History**: Explain chart interpretation
3. **PIM - Review Queue**: Explain approval workflow

### Priority 3 (Nice to Have)

1. **Dashboard widgets**: Add "?" icons with more detailed explanations
2. **Export buttons**: Tooltip explaining export format options
3. **Filter dropdowns**: Explain filter behavior

---

## Implementation Checklist

To add tooltips to a new area:

- [ ] Identify fields/features that need explanation
- [ ] Write concise, actionable help text
- [ ] Add `helperText()` to Filament forms OR
- [ ] Add tooltip HTML to Blade views
- [ ] Test tooltip display on desktop and mobile
- [ ] Verify accessibility (keyboard navigation, screen readers)
- [ ] Update this documentation

---

## Support Contact Information

All help tooltips should direct users to documentation or support when needed:

**Standard help text suffix**:

> "See [User Guide](link) for details or contact support@silvertreebrands.com."

**Locations where support info is shown**:

- ✅ Error pages (BigQuery errors, 500 errors)
- ✅ All user guides (footer)
- ✅ FAQ page
- ✅ Troubleshooting guide
- ❌ **Missing**: Footer of main panels (consider adding)

---

## Testing Help Tooltips

### Manual Testing

1. **Hover test**: Verify tooltip appears on hover
2. **Click test**: Tooltip dismisses appropriately
3. **Responsive test**: Check on mobile/tablet
4. **Keyboard test**: Tab navigation works
5. **Readability test**: Text is clear and helpful

### User Testing

1. Give new user a task
2. Observe if they use tooltips
3. Ask if tooltips were helpful
4. Gather feedback on clarity

---

## Future Enhancements

Consider adding:

1. **Interactive tooltips**: With links to docs
2. **Video tooltips**: Embedded GIFs or short videos
3. **Contextual help panel**: Sidebar with comprehensive help
4. **Onboarding tour**: First-time user guided walkthrough
5. **Search help**: Full-text search across all help content

---

**Document Version**: 1.0
**Last Updated**: 2025-12-14
**Maintained By**: Development Team
