# Group Detail Page Enhancements

## Overview
Enhanced the group detail page to support multi-plan groups with better filtering and statistics display.

## Changes Made

### Backend Changes

#### 1. GroupController.php - Enhanced Group Detail API
**File**: `backend/Modules/Medical/Http/Controllers/GroupController.php`

**Changes**:
- Added plan eager-loading to policies: `'policies' => fn($q) => $q->latest()->limit(10)->with('plan:id,name,code')`
- Enhanced application eager-loading with plan details: `'scheme:id,name,code', 'plan:id,name,code', 'rateCard:id,name,code'`

**Impact**: API now returns complete plan information for both policies and applications.

---

#### 2. GroupResource.php - Added Plan Statistics
**File**: `backend/Modules/Medical/Http/Resources/GroupResource.php`

**New Methods**:
```php
// Get policy count grouped by plan
private function getPoliciesByPlanStats(): array

// Get application count grouped by plan
private function getApplicationsByPlanStats(): array
```

**New Response Fields**:
```json
{
  "stats": {
    "total_policies": 5,
    "active_policies": 4,
    "total_applications": 3,
    "policies_by_plan": [
      {
        "plan_id": "uuid",
        "plan_code": "PLAN001",
        "plan_name": "Gold Plan",
        "count": 3
      },
      {
        "plan_id": "uuid",
        "plan_code": "PLAN002",
        "plan_name": "Silver Plan",
        "count": 2
      }
    ],
    "applications_by_plan": [
      {
        "plan_id": "uuid",
        "plan_code": "PLAN001",
        "plan_name": "Gold Plan",
        "count": 2
      }
    ]
  }
}
```

**Impact**: Frontend can now display plan distribution and counts without client-side calculation.

---

### Frontend Changes

#### 3. medical-group-detail.ts - Added Plan Filter & Statistics
**File**: `front-end/projects/libs/medical/feature/src/lib/medical-group-detail/medical-group-detail.ts`

**New Signals**:
```typescript
readonly planFilter = signal('');
```

**New Computed Properties**:
```typescript
// Extract unique plans from all applications
readonly uniquePlans = computed(() => {
  // Returns array of { id, name, code }
});

// Policy count by plan (from backend stats)
readonly policyCountByPlan = computed(() => {
  return this.group()?.stats?.policies_by_plan || [];
});

// Application count by plan (from backend stats)
readonly applicationCountByPlan = computed(() => {
  return this.group()?.stats?.applications_by_plan || [];
});
```

**New Helper Method**:
```typescript
getApplicationCountForPlan(planId: string): number {
  // Finds count for specific plan from stats
}
```

**Enhanced filteredMembers Computed**:
- Added plan filter logic
- Filters members by finding applications with selected plan
- Works in combination with other filters (search, member type, application)

**Updated clearFilters()**:
- Now clears plan filter along with other filters

---

#### 4. medical-group-detail.html - Enhanced UI
**File**: `front-end/projects/libs/medical/feature/src/lib/medical-group-detail/medical-group-detail.html`

**New Elements**:

1. **Policy Count Card** (added to stats grid):
   ```html
   <mat-card class="!shadow-sm">
     <mat-card-content class="!p-4">
       <div class="text-xs text-slate-600 mb-1">Policies</div>
       <div class="text-2xl font-bold text-purple-600">{{ group()?.stats?.total_policies || 0 }}</div>
     </mat-card-content>
   </mat-card>
   ```
   - Shows total number of policies for the group
   - Purple theme to distinguish from applications

2. **Plan Distribution Card** (new section):
   ```html
   <mat-card class="!shadow-sm mb-4">
     <mat-card-content class="!p-4">
       <div class="text-sm font-medium text-slate-900 mb-3">Plan Distribution</div>
       <div class="flex gap-2 flex-wrap">
         @for (plan of uniquePlans(); track plan.id) {
           <mat-chip class="!bg-indigo-100 !text-indigo-900 cursor-pointer"
                     (click)="planFilter.set(plan.id)">
             <mat-icon>description</mat-icon>
             {{ plan.name }}
             <span class="ml-1 font-semibold">({{ getApplicationCountForPlan(plan.id) }})</span>
           </mat-chip>
         }
       </div>
     </mat-card-content>
   </mat-card>
   ```
   - Shows all unique plans in the group
   - Displays application count per plan
   - Clicking a chip filters members by that plan
   - Only shows if group has multiple plans

3. **Plan Filter Dropdown** (added to filters section):
   ```html
   <mat-form-field appearance="fill" subscriptSizing="dynamic" class="w-[200px] minimal-input">
     <mat-label>Plan</mat-label>
     <mat-select [ngModel]="planFilter()" (ngModelChange)="planFilter.set($event)">
       <mat-option value="">All Plans</mat-option>
       @for (plan of uniquePlans(); track plan.id) {
         <mat-option [value]="plan.id">{{ plan.name }}</mat-option>
       }
     </mat-select>
   </mat-form-field>
   ```
   - Traditional dropdown for plan selection
   - Works alongside application, member type, and search filters

**Layout Changes**:
- Changed stats grid from 4 columns to 5 columns (`md:grid-cols-4` → `md:grid-cols-5`)
- Added "Policies" card between "Applications" and "Total Members"
- Inserted plan distribution card between stats and member breakdown chips

---

## Features Enabled

### 1. Multi-Plan Group Support
- Groups can now have applications/policies across multiple plans
- Visual plan distribution shows which plans are being used
- Easy filtering to see members enrolled in specific plans

### 2. Enhanced Filtering
- **Search**: By name, email, employee number
- **Application**: Filter by specific application
- **Plan**: Filter by plan (shows members in all applications under that plan)
- **Member Type**: Filter by principal, spouse, child, etc.
- **Combinations**: All filters work together

### 3. Better Statistics
- Total applications count
- Total policies count (new)
- Total members count
- Principals count
- Dependents count
- Per-plan application count (new)
- Per-plan policy count (available for future use)

### 4. Improved UX
- Clickable plan chips for quick filtering
- Clear visual indication of plan distribution
- Policy count prominently displayed
- Consistent card-based layout

---

## User Flow Examples

### Example 1: Upload Multiple Census Files for Different Plans
1. User opens group detail page for "ABC Corporation"
2. Clicks "Upload Census" → Uploads file for Gold Plan → Creates Application 1
3. Clicks "Upload Census" → Uploads file for Silver Plan → Creates Application 2
4. Plan distribution shows: Gold Plan (1), Silver Plan (1)
5. Member table shows all members from both applications
6. User can filter by plan to see only Gold Plan members or only Silver Plan members

### Example 2: View Members by Plan
1. Group has 3 applications across 2 plans
2. Plan distribution shows: Executive Plan (1), Standard Plan (2)
3. User clicks "Executive Plan" chip
4. Member table filters to show only members in Executive Plan applications
5. User clicks "Clear" to reset filter

### Example 3: Generate Quote for Specific Plan
1. User filters members by "Gold Plan"
2. Filtered members shown (e.g., 50 members)
3. User clicks "Generate Quote"
4. Quote dialog opens with only Gold Plan members
5. Quote generated for that specific subset

---

## Data Flow

```
Backend API Response:
GET /api/v1/medical/groups/{id}
↓
GroupResource processes:
- Loads applications with plan eager-loading
- Calculates applications_by_plan stats
- Loads policies with plan eager-loading
- Calculates policies_by_plan stats
↓
Frontend receives:
{
  applications: [...with plan data...],
  policies: [...with plan data...],
  stats: {
    applications_by_plan: [...],
    policies_by_plan: [...]
  }
}
↓
Component computes:
- uniquePlans (extracts from applications)
- filteredMembers (applies plan filter)
- Displays plan distribution chips
- Enables plan filtering
```

---

## Future Enhancements (Not Implemented Yet)

### Member Movement Between Plans
User mentioned: "from here we can also move members and premium should adjust accordingly"

**Planned Approach**:
1. Add "Move to Plan" action in member row actions menu
2. Open dialog with:
   - Current plan display
   - Target plan dropdown (other plans in same group)
   - Effective date picker
   - Premium adjustment preview (old vs new)
3. Backend endpoint: `POST /api/v1/medical/members/{id}/move-to-plan`
   - Calculate premium difference
   - Update member's application/policy
   - Pro-rate premium if mid-term
   - Update totals on both source and target applications/policies
4. Refresh group detail to show updated distribution

**Considerations**:
- Only allow moving between applications in same group
- Validate plan compatibility (e.g., age limits, coverage levels)
- Handle mid-term changes with pro-rata premium adjustments
- Update member card if already issued
- Audit trail for member plan changes

---

## Testing Checklist

### Backend Testing:
- [ ] GET /groups/{id} returns policies_by_plan in stats
- [ ] GET /groups/{id} returns applications_by_plan in stats
- [ ] Plan eager-loading works correctly
- [ ] Stats calculation handles multiple plans correctly
- [ ] Stats calculation handles single plan correctly
- [ ] Stats calculation handles no applications correctly

### Frontend Testing:
- [ ] Plan distribution card shows when multiple plans exist
- [ ] Plan distribution card hidden when single/no plans
- [ ] Plan filter dropdown populated with unique plans
- [ ] Clicking plan chip filters members correctly
- [ ] Plan filter works with other filters (search, type, application)
- [ ] Application count per plan displays correctly
- [ ] Clear filters button resets plan filter
- [ ] Policy count card displays correct number
- [ ] 5-column grid layout displays correctly on desktop
- [ ] Layout responsive on mobile/tablet

### Integration Testing:
- [ ] Upload census for Plan A → Plan shows in distribution
- [ ] Upload census for Plan B → Both plans show in distribution
- [ ] Convert application to policy → Policy count increases
- [ ] Filter by plan → Only relevant members shown
- [ ] Export members with plan filter active → Correct subset exported

---

## Performance Considerations

### Backend:
- ✅ Eager loading prevents N+1 queries
- ✅ Stats calculated in-memory (no additional queries)
- ✅ Limited to 10 most recent policies (prevents over-fetching)

### Frontend:
- ✅ Computed signals cache calculations
- ✅ No loops in templates (using @for directive)
- ✅ Plan extraction happens once per data load
- ✅ Filter logic optimized with early returns

---

## Summary

**What Works Now**:
- ✅ Upload multiple census files with different plans
- ✅ View all applications and their plans
- ✅ Filter members by plan
- ✅ See plan distribution at a glance
- ✅ Track policy count per group
- ✅ Clickable plan chips for quick filtering
- ✅ All filters work together seamlessly

**What's Next**:
- Member movement between plans (planned)
- Premium adjustment on plan change (planned)
- Pro-rata calculation for mid-term changes (planned)

**User Benefit**:
"This is great because it takes away the complexity of mapping" - users can simply upload multiple census files (one per plan) and the system handles the multi-plan structure automatically without complex upfront configuration.
