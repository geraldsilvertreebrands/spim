# Pipeline UI User Guide

## Quick Start: Creating Your First Pipeline

### Step 1: Navigate to Pipelines
```
Settings → Pipelines → Create
```

### Step 2: Basic Configuration
```
┌─────────────────────────────────────┐
│ Pipeline Configuration              │
├─────────────────────────────────────┤
│ Entity Type: [Product        ▼]    │
│                                     │
│ Target Attribute: [Description ▼]  │
│   (Only shows attributes without    │
│    existing pipelines)              │
│                                     │
│ Pipeline Name: My First Pipeline    │
│   (Optional)                        │
│                                     │
│              [Save]                 │
└─────────────────────────────────────┘
```

### Step 3: Configure Modules (Auto-redirected after save)

The edit page has **3 tabs**:

#### Configuration Tab
```
┌────────────────────────────────────────────────────┐
│ [Configuration] [Statistics] [Evaluations]        │
├────────────────────────────────────────────────────┤
│                                                    │
│ Pipeline Information                               │
│ ├─ Name: My First Pipeline                        │
│ ├─ Entity Type: Product (read-only)               │
│ ├─ Target Attribute: Description (read-only)      │
│ ├─ Version: 1                                     │
│ └─ Last Updated: 2 minutes ago                    │
│                                                    │
│ Processing Modules                                 │
│ ┌──────────────────────────────────────────────┐  │
│ │ 📁 Attributes                          [↕][x]│  │
│ │ ├─ Description: Load attribute values...     │  │
│ │ ├─ Attributes: [Name, Brand, Category  ▼]   │  │
│ │ └─ (Drag to reorder)                         │  │
│ └──────────────────────────────────────────────┘  │
│                                                    │
│ ┌──────────────────────────────────────────────┐  │
│ │ 🔧 AI Prompt                           [↕][x]│  │
│ │ ├─ Description: Generate values using...     │  │
│ │ ├─ Prompt: [Generate compelling product...] │  │
│ │ ├─ Schema Template: [Text ▼]                 │  │
│ │ ├─ Output Schema: {...}                      │  │
│ │ └─ Model: [GPT-4o Mini ▼]                    │  │
│ └──────────────────────────────────────────────┘  │
│                                                    │
│              [+ Add Module]                        │
│                                                    │
│                           [Save]                   │
└────────────────────────────────────────────────────┘
```

**Module Block Types**:
- 📁 **Attributes** (Source) - Must be first
- 🔧 **AI Prompt** (Processor) - OpenAI integration
- 🔧 **Calculation** (Processor) - JavaScript code

**Validation**:
- ⚠️ First module must be a source
- ⚠️ Minimum 2 modules required
- ✅ Drag to reorder
- ✅ Version auto-increments on save

#### Statistics Tab
```
┌────────────────────────────────────────────────────┐
│ [Configuration] [Statistics] [Evaluations]        │
├────────────────────────────────────────────────────┤
│                                                    │
│ Last Run Stats                                     │
│ ├─ Last Run: 5 minutes ago                        │
│ ├─ Status: completed                              │
│ ├─ Entities Processed: 245                        │
│ ├─ Failed: 0                                      │
│ └─ Tokens (In/Out): 12,450 / 8,920                │
│                                                    │
│ Token Usage (Last 30 Days)                         │
│ └─ Total: 245,890 | Avg per entity: 1,003        │
│                                                    │
└────────────────────────────────────────────────────┘
```

#### Evaluations Tab
```
┌────────────────────────────────────────────────────┐
│ [Configuration] [Statistics] [Evaluations (2❌)]  │
├────────────────────────────────────────────────────┤
│                                                    │
│ Evaluation Test Cases                              │
│                                                    │
│ ┌──────────────────────────────────────────────┐  │
│ │ ▶ Entity: 01JAXXX123 ✅                [x]   │  │
│ └──────────────────────────────────────────────┘  │
│                                                    │
│ ┌──────────────────────────────────────────────┐  │
│ │ ▼ Entity: 01JAXXX456 ❌                [x]   │  │
│ │ ┌──────────────────────────────────────────┐ │  │
│ │ │ Entity ID: 01JAXXX456                    │ │  │
│ │ │                                          │ │  │
│ │ │ Desired Output (JSON):                  │ │  │
│ │ │ ┌──────────────────────────────────────┐ │ │  │
│ │ │ │ {                                    │ │ │  │
│ │ │ │   "value": "Expected Description",   │ │ │  │
│ │ │ │   "justification": "Because...",     │ │ │  │
│ │ │ │   "confidence": 0.95                 │ │ │  │
│ │ │ │ }                                    │ │ │  │
│ │ │ └──────────────────────────────────────┘ │ │  │
│ │ │                                          │ │  │
│ │ │ Notes: Test case for product XYZ        │ │  │
│ │ │                                          │ │  │
│ │ │ ┌───────────────────────────────────┐   │ │  │
│ │ │ │ Input Hash: a3f2...                │   │ │  │
│ │ │ │ Last Actual: {"value": "Wrong"}    │   │ │  │
│ │ │ │ Status: ❌ Failing                  │   │ │  │
│ │ │ └───────────────────────────────────┘   │ │  │
│ │ └──────────────────────────────────────────┘ │  │
│ └──────────────────────────────────────────────┘  │
│                                                    │
│              [+ Add Evaluation]                    │
│                                                    │
│                           [Save]                   │
└────────────────────────────────────────────────────┘
```

**Header Actions** (Available on all tabs):
```
[Run Pipeline] [Run Evals] [Delete]
```

## Module Configuration Details

### 1. Attributes Source Module
```
Purpose: Load existing attribute values as inputs
Required: Must be first module

Configuration:
├─ Attributes: (Multi-select)
│  └─ Select attributes to load (e.g., Name, Brand, Category)
│
Output: Array of attribute values
Example: { "Name": "Widget", "Brand": "Acme", "Category": "Tools" }
```

### 2. AI Prompt Processor Module
```
Purpose: Use OpenAI to generate values from inputs
Type: Processor (2nd position or later)

Configuration:
├─ Prompt: (Textarea)
│  └─ Instructions for AI (inputs auto-appended)
│
├─ Schema Template: (Select)
│  ├─ Text (string + justification + confidence)
│  ├─ Integer (number + justification + confidence)
│  ├─ Boolean (true/false + justification + confidence)
│  ├─ Array (strings + justification + confidence)
│  └─ Custom (edit JSON manually)
│
├─ Output Schema: (JSON)
│  └─ Auto-filled based on template, editable
│
└─ Model: (Select)
   ├─ GPT-4o (latest, most capable)
   ├─ GPT-4o Mini (faster, cheaper) ← Recommended
   └─ GPT-4 Turbo (previous generation)

Output: Structured JSON per schema
Tracks: Token usage per run
```

### 3. Calculation Processor Module
```
Purpose: Execute JavaScript to transform inputs
Type: Processor (2nd position or later)

Configuration:
└─ JavaScript Code: (Enhanced Textarea, 25 rows, monospace)
   ├─ Available variables:
   │  ├─ $json (all inputs as object)
   │  ├─ $value (current value in pipeline)
   │  └─ $meta (metadata)
   │
   └─ Must return:
      {
        value: <any>,
        justification: <string>,
        confidence: <0-1>
      }

Examples provided in placeholder:
1. Calculation: quantity × price
2. Conditional: stock > 100 ? "In Stock" : "Low Stock"
3. String manipulation: brand + name, uppercase

Output: Runs in sandboxed Node.js (10s timeout)
```

## Evaluation Workflow

### 1. Create Evaluation
```
1. Go to Evaluations tab
2. Click "Add Evaluation"
3. Fill in:
   ├─ Entity ID: (ULID from entities table)
   ├─ Desired Output: (JSON of expected result)
   └─ Notes: (Why this test case matters)
4. Save
```

### 2. Run Evaluations
```
Method 1: Click "Run Evals" button in header
Method 2: Evals auto-run after pipeline executions
```

### 3. Interpret Results
```
Status Indicators:
├─ ✅ Passing: actual_output === desired_output
├─ ❌ Failing: actual_output !== desired_output
├─ —  Not run: Never executed
└─ Badge on tab: Shows count of failing evals
```

### 4. When Inputs Change
```
Pipeline tracks input_hash for each entity
If hash changes:
├─ Old eval may be outdated
├─ Input Hash field shows hash mismatch
└─ Re-run evals to update actual output
```

## Common Workflows

### Create Simple Pipeline
```
1. Create → Select Entity + Attribute
2. Add Attributes source → Pick 2-3 inputs
3. Add AI Prompt → Write prompt
4. Save
5. Run Pipeline
6. Check Statistics tab
```

### Create Complex Pipeline
```
1. Create → Select Entity + Attribute
2. Add Attributes source → Pick many inputs
3. Add Calculation → Pre-process inputs (e.g., format)
4. Add AI Prompt → Generate value
5. Add Calculation → Post-process (e.g., cleanup)
6. Save
7. Add 3-5 evals for edge cases
8. Run Evals
9. Iterate on prompts until evals pass
```

### Debug Failing Eval
```
1. Go to Evaluations tab
2. Expand failing eval (❌)
3. Compare:
   ├─ Desired Output: What you wanted
   └─ Last Actual Output: What pipeline produced
4. Check Input Hash:
   ├─ If changed → Inputs changed, eval may be outdated
   └─ If same → Pipeline logic needs adjustment
5. Fix module configuration
6. Run Evals again
```

## Tips & Best Practices

### Module Configuration
- ✅ **Do**: Start with Attributes source, add processors as needed
- ✅ **Do**: Test with 1-2 entities before running on all
- ✅ **Do**: Use descriptive module names in prompts
- ❌ **Don't**: Add multiple source modules (not supported)
- ❌ **Don't**: Skip validation errors (they prevent bad pipelines)

### AI Prompts
- ✅ **Do**: Be specific and provide examples
- ✅ **Do**: Use schema templates (Text, Integer, etc.)
- ✅ **Do**: Start with GPT-4o Mini (cheaper)
- ✅ **Do**: Monitor token usage in Statistics tab
- ❌ **Don't**: Write prompts longer than needed (costs more)
- ❌ **Don't**: Forget justification and confidence in schema

### JavaScript Code
- ✅ **Do**: Use the provided examples as templates
- ✅ **Do**: Handle null/undefined inputs gracefully
- ✅ **Do**: Return all three fields (value, justification, confidence)
- ✅ **Do**: Test complex logic outside pipeline first
- ❌ **Don't**: Assume inputs always exist
- ❌ **Don't**: Use async/await (not supported in sandbox)
- ❌ **Don't**: Try to access external APIs (sandbox blocks network)

### Evaluations
- ✅ **Do**: Create evals for edge cases (empty, null, special chars)
- ✅ **Do**: Document why each eval matters (use Notes)
- ✅ **Do**: Re-run after changing pipeline logic
- ✅ **Do**: Aim for 100% passing before production use
- ❌ **Don't**: Skip evals (they catch regressions)
- ❌ **Don't**: Use production entities only (add synthetic test cases)

## Troubleshooting

### "First module must be a source module"
**Problem**: You added a processor first, or removed the source  
**Solution**: Add/move Attributes module to first position

### "Pipeline must have at least one processor module"
**Problem**: Only have source module, no processors  
**Solution**: Add AI Prompt or Calculation module

### "Invalid JSON" for Eval
**Problem**: Desired output is not valid JSON  
**Solution**: Use a JSON validator, ensure proper format:
```json
{
  "value": "Your expected value",
  "justification": "Why this is correct",
  "confidence": 0.95
}
```

### Module forms not saving
**Problem**: Module configuration lost after save  
**Solution**: Check browser console for errors, ensure all required fields filled

### Evals always failing
**Problem**: Actual output doesn't match desired  
**Solution**:
1. Check exact format (spaces, quotes matter in JSON comparison)
2. Run pipeline manually and inspect actual output
3. Adjust desired output to match expected format
4. Or fix pipeline logic to produce correct format

### Pipeline not running
**Problem**: Clicked "Run Pipeline" but nothing happens  
**Solution**:
1. Check Horizon dashboard (queue jobs)
2. Ensure modules are configured
3. Check logs for errors
4. Verify OpenAI API key set (for AI modules)

## Keyboard Shortcuts

```
Within Module Builder:
├─ Drag/Drop: Reorder modules
├─ Click [x]: Delete module
└─ Click ▼: Expand/collapse module

Within Evaluations:
├─ Click ▼: Expand/collapse eval
└─ Click [x]: Delete eval
```

## Next Steps

1. ✅ Create your first pipeline
2. ✅ Add 2-3 evaluations
3. ✅ Run and verify results
4. 📖 Read phase6.md for advanced features
5. 📖 Check PIPELINE_IMPLEMENTATION.md for technical details

## Support

- Technical docs: `/docs/phase6.md`
- Implementation details: `PIPELINE_IMPLEMENTATION.md`
- Summary: `PIPELINE_UI_SUMMARY.md`
- This guide: `PIPELINE_UI_GUIDE.md`

