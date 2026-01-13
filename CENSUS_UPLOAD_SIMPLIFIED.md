# Census Upload Dialog - Simplified

## Changes Made

### Problem Identified
The multi-plan mapping feature in the census upload dialog created a dependency issue:
1. **Scheme** → **Plans** → **Rate Cards** (cascade dependency)
2. When multi-plan was enabled, plans were selected in the mapping step
3. Rate cards depend on a plan being selected
4. This created a broken flow where rate cards couldn't load

### Solution Implemented
**Simplified the census upload dialog to single-plan only** and moved multi-plan functionality to the group detail page.

## New Flow

### Census Upload Dialog (3 Steps - Simplified)

```
┌─────────────────────────────────────────────────────────┐
│ Step 1: Upload                                           │
│ - Drag & drop or browse for CSV/Excel file              │
│ - Download template link                                 │
│ - File validation (max 10MB, CSV/XLSX/XLS)              │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│ Step 2: Preview                                          │
│ - Summary cards (Total, Valid, Invalid)                 │
│ - Member type breakdown                                  │
│ - Preview table (first 5 rows)                          │
│ - Error messages if validation fails                    │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│ Step 3: Configure                                        │
│ - Select Scheme (loads plans)                           │
│ - Select Plan (loads rate cards)                        │
│ - Select Rate Card (auto-select if only one)            │
│ - Inception Date                                         │
│ - Billing Frequency                                      │
└─────────────────────────────────────────────────────────┘
                          ↓
               Create Single Application
```

### Key Changes

#### Removed:
- ❌ Multi-plan toggle in preview step
- ❌ Plan mapping step (step 3.5)
- ❌ Plan mapping form (mapping type selector)
- ❌ Tier-to-plan mapping UI
- ❌ `createMultiPlanFromCensus()` call in dialog

#### Kept:
- ✅ Simple 3-step wizard
- ✅ Cascade selection (Scheme → Plan → Rate Card)
- ✅ Single application creation
- ✅ Clear, linear flow

#### Files Modified:

**TypeScript**: `medical-census-upload-dialog.ts`
- Removed: `enableMultiPlan`, `mappingType`, `uniqueValues`, `planMapping`, `memberCountByValue` signals
- Removed: `analyzeCensusForMapping()`, `updatePlanMapping()`, `onMappingTypeChange()`, `toggleMultiPlan()` methods
- Removed: Multi-plan branch in `createApplication()`
- Simplified: `goToConfiguration()` - now just navigates to configure step

**HTML**: `medical-census-upload-dialog.html`
- Removed: Multi-plan step indicator
- Removed: Multi-plan toggle button in preview
- Removed: Plan mapping step content
- Removed: All multi-plan conditional logic
- Result: Clean 3-step wizard

## Cascade Selection Flow (Fixed)

```typescript
// Scheme Change
scheme_id selected
    ↓
Load plans for scheme
    ↓
Enable plan dropdown
    ↓
Clear rate_card selection

// Plan Change
plan_id selected
    ↓
Load rate cards for plan
    ↓
Enable rate card dropdown
    ↓
Auto-select if only 1 active rate card
```

This ensures:
- ✅ Plans load after scheme selection
- ✅ Rate cards load after plan selection
- ✅ No dependency conflicts
- ✅ Proper form validation

## Future: Multi-Plan in Group Detail Page

The multi-plan functionality will be better suited in the **Group Detail Page** where:

1. **View existing applications** under a group
2. **Add new application** with different plan (from group detail)
3. **Move members** between applications/plans
4. **View plan distribution** across the group

### Recommended Approach:

**Group Detail Page Enhancement:**
```
┌────────────────────────────────────────────────────────┐
│ Group: ABC Corp                              [← Back]  │
├────────────────────────────────────────────────────────┤
│ Applications (3)                                       │
│                                                         │
│ [Application 1 - Executive Plan]   15 members          │
│ [Application 2 - Gold Plan]        45 members          │
│ [Application 3 - Silver Plan]      90 members          │
│                                                         │
│ [+ Add New Application]  [Upload Census]               │
└────────────────────────────────────────────────────────┘
```

**When "Upload Census" clicked from group detail:**
1. Upload census (same dialog - simplified)
2. After upload, show **quick plan selector**:
   - "Create new application with plan: [Select Plan]"
   - "Add to existing application: [Select Application]"
3. Create application/add members
4. Return to group detail

**Benefits:**
- ✅ Census upload stays simple
- ✅ Multi-plan is a group-level action (makes more sense)
- ✅ Better UX for moving members between plans
- ✅ Clear plan distribution visualization

## Group Detail Page Status

### Already Implemented ✅
- ✅ Card-based layout with stats
- ✅ Back button using `lib-page-header`
- ✅ Member filtering (frontend only)
- ✅ Member stats breakdown
- ✅ Applications list
- ✅ Census upload button
- ✅ Group quote button
- ✅ Export to CSV

### Structure (Already Consistent with Plan Detail):
```html
<lib-page-header
  [title]="group()!.name"
  [subtitle]="'Corporate Group · ' + group()!.code"
  (back)="backToGroups()">
  <div actions>
    <button>Upload Census</button>
    <button>Generate Quote</button>
  </div>
</lib-page-header>

<div class="page-content p-6">
  <!-- Stat Cards -->
  <!-- Applications Section -->
  <!-- Members Table -->
</div>
```

This matches the pattern used in `medical-plan-detail.html`.

## Template File

The census template now includes fields for multi-plan mapping (even though not used in dialog):

```csv
first_name,last_name,date_of_birth,gender,email,phone,id_number,employee_number,member_type,relationship,principal_employee_number,salary_band,department,job_title
John,Doe,1985-06-15,M,john.doe@company.com,+1234567890,ID123456,EMP001,principal,,,Executive,Executive Office,CEO
```

**Fields available for future multi-plan:**
- `salary_band`: Executive, Senior, Mid, Junior
- `department`: IT, Sales, Operations, HR, etc.
- `job_title`: CEO, Manager, Officer, etc.

These can be used later when implementing multi-plan from group detail page.

## Summary

**Before (Broken):**
- 4-step wizard with plan mapping
- Rate cards couldn't load due to dependency issues
- Complex UX with multiple concerns mixed

**After (Fixed):**
- 3-step simple wizard
- Proper cascade selection (Scheme → Plan → Rate Card)
- Single application creation
- Clean, focused UX

**Next Step:**
- Multi-plan functionality belongs in **Group Detail Page**
- Better UX for managing multiple applications under one group
- Clearer plan distribution visualization
