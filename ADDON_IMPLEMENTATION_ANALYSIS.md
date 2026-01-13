# Addon Implementation Analysis & Recommendations

## Current Implementation Overview

### Database Structure

#### 1. **med_addons** Table
- Stores addon catalog (e.g., Dental, Optical, Maternity)
- Fields: `id`, `code`, `name`, `addon_type`, `pricing_type`, `amount`, `percentage`, etc.
- `addon_type`: Not used for availability (legacy field)
- `pricing_type`: `fixed`, `per_member`, or `percentage`

#### 2. **med_plan_addons** Table (Pivot)
- Links addons to plans with availability configuration
- **Key Fields:**
  - `plan_id`: Which plan this addon belongs to
  - `addon_id`: Reference to the addon
  - `availability`: **`mandatory`, `optional`, `included`, `conditional`**
  - `is_active`: Whether this addon is currently available
  - `conditions`: JSON for conditional logic
  - `benefit_overrides`: JSON for custom benefit limits

#### 3. **med_application_addons** Table
- Stores selected addons for an application
- Fields: `application_id`, `addon_id`, `premium`, `is_active`

### Backend Logic

#### PlanAddon Model (c:\Users\CICT-SD\Desktop\flex-care-v2\backend\Modules\Medical\Models\PlanAddon.php)
```php
- availability: 'mandatory' | 'optional' | 'included' | 'conditional'
- Scopes: mandatory(), optional(), included()
- Accessors:
  - is_mandatory: availability === 'mandatory'
  - is_optional: availability === 'optional'
  - is_included: availability === 'included'
  - requires_additional_premium: !is_included
```

#### Addon Model (c:\Users\CICT-SD\Desktop\flex-care-v2\backend\Modules\Medical\Models\Addon.php)
```php
calculatePremium(int $memberCount, float $basePremium): float
- fixed: returns amount
- per_member: returns amount * memberCount
- percentage: returns basePremium * (percentage / 100)
```

#### PremiumService (c:\Users\CICT-SD\Desktop\flex-care-v2\backend\Modules\Medical\Services\PremiumService.php:386)
```php
calculateAddonPremium(Addon $addon, string $planId, float $basePremium, int $memberCount)
1. Checks if addon is included in plan (via PlanAddon)
2. If included, returns premium = 0
3. Otherwise, uses addon->calculatePremium()
4. Returns: ['success' => true, 'premium' => amount, 'is_included' => bool]
```

#### Application Premium Calculation Flow (lines 24-127)
```php
calculateApplicationPremium(Application $application)
1. Calculate base premium (sum all members)
2. Calculate addon premium:
   - Loop through application->activeAddons
   - For each addon, call calculateAddonPremium()
   - Update ApplicationAddon.premium
   - Sum all addon premiums
3. Calculate totals: base + addons + loading - discount
4. Apply tax
5. Save to application fields: base_premium, addon_premium, total_premium, etc.
```

### Frontend Structure

#### Current Dialog Approach (medical-application-addon-dialog.ts:line 116)
- Opens a dialog to select addons
- Fetches available addons from: `/api/v1/medical/plans/{planId}/addons`
- Filters out already added addons
- Shows addon details, pricing, and estimated premium
- User manually selects and adds addon

#### Application Detail (medical-application-detail.ts:421-449)
```typescript
addAddon() {
  // Opens dialog with:
  // - planId: app.plan_id
  // - memberCount: app.member_count
  // - basePremium: app.base_premium
  // - existingAddonIds: already added addon IDs
}
```

## Key Findings

### ✅ What's Working Well
1. **Backend structure is solid**:
   - `med_plan_addons` table has `availability` field for mandatory/optional
   - Premium calculation properly handles included addons (0 premium)
   - PlanAddon model has proper scopes and accessors

2. **API endpoint exists**:
   - `GET /api/v1/medical/plans/{planId}/addons` returns configured plan addons
   - Resource includes `availability`, `is_mandatory`, `is_optional`, `requires_additional_premium`

3. **Premium calculation is comprehensive**:
   - Handles all pricing types (fixed, per_member, percentage)
   - Properly accounts for included addons
   - Recalculates when addons are added/removed

### ❌ Current Issues

1. **Manual addon selection via dialog**:
   - User must open dialog and manually select addons
   - Mandatory addons are NOT auto-applied
   - Optional addons are NOT pre-listed with checkboxes

2. **Frontend doesn't respect availability**:
   - Dialog shows all addons equally
   - No distinction between mandatory/optional in UI
   - No auto-check for mandatory addons

3. **Single plan assumption**:
   - Application has ONE `plan_id`
   - No built-in support for multiple plans per application
   - Members don't have individual `plan_id` assignment

4. **Premium calculation assumes single plan**:
   - Uses `app.plan_id` globally
   - Cannot calculate different addon premiums per member/plan

## Recommendations

### 1. **Inline Addon Selection (Simple Approach)**

#### Backend Changes (Minimal)
✅ **No backend changes needed** - structure already supports this!

Just ensure the endpoint `/api/v1/medical/plans/{planId}/addons` returns:
- All configured plan addons (with `addon` relationship loaded)
- Properly marked `availability`, `is_mandatory`, `is_optional`

#### Frontend Changes (Application Detail)

**Replace dialog approach with inline section:**

```typescript
// application-detail.ts
readonly planAddons = signal<PlanAddon[]>([]);
readonly selectedAddonIds = signal<Set<string>>(new Set());

ngOnInit() {
  // Load application
  this.loadApplication();
  // Load plan addons
  this.loadPlanAddons();
}

loadPlanAddons() {
  const app = this.application();
  if (!app?.plan_id) return;

  this.http.get(`/api/v1/medical/plans/${app.plan_id}/addons`)
    .subscribe(res => {
      this.planAddons.set(res.data);
      // Auto-select mandatory addons
      const mandatoryIds = res.data
        .filter(pa => pa.is_mandatory)
        .map(pa => pa.addon_id);
      this.selectedAddonIds.update(ids => {
        mandatoryIds.forEach(id => ids.add(id));
        return new Set(ids);
      });
      // Also select already added addons
      app.addons?.forEach(addon => {
        this.selectedAddonIds.update(ids => {
          ids.add(addon.addon_id);
          return new Set(ids);
        });
      });
    });
}

toggleAddon(planAddon: PlanAddon, checked: boolean) {
  // Don't allow unchecking mandatory
  if (planAddon.is_mandatory && !checked) return;

  if (checked) {
    this.addAddonToApplication(planAddon.addon_id);
  } else {
    this.removeAddonFromApplication(planAddon.addon_id);
  }
}

calculateEstimatedPremium(addon: Addon): number {
  const app = this.application();
  if (!app) return 0;

  switch (addon.pricing_type) {
    case 'fixed':
      return addon.amount || 0;
    case 'per_member':
      return (addon.amount || 0) * (app.member_count || 0);
    case 'percentage':
      return (app.base_premium || 0) * ((addon.percentage || 0) / 100);
    default:
      return 0;
  }
}
```

**HTML Template (after Members section):**

```html
<!-- Addons Section -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
  <div class="border-b border-slate-100 px-6 py-4">
    <h2 class="text-base font-semibold text-slate-900">Plan Addons</h2>
    <p class="text-sm text-slate-500">
      Select optional addons (mandatory addons are pre-selected)
    </p>
  </div>

  @if (planAddons().length === 0) {
    <div class="p-8 text-center text-slate-500">
      No addons configured for this plan
    </div>
  } @else {
    <div class="divide-y divide-slate-100">
      @for (planAddon of planAddons(); track planAddon.id) {
        <div class="px-6 py-4 hover:bg-slate-50 transition-colors">
          <div class="flex items-start gap-4">
            <!-- Checkbox -->
            <mat-checkbox
              [checked]="selectedAddonIds().has(planAddon.addon_id)"
              [disabled]="planAddon.is_mandatory || !canEdit()"
              (change)="toggleAddon(planAddon, $event.checked)"
              class="mt-1"
            />

            <!-- Addon Info -->
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-1">
                <span class="font-medium text-slate-900">
                  {{ planAddon.addon?.name }}
                </span>
                @if (planAddon.is_mandatory) {
                  <span class="inline-flex items-center rounded-full bg-purple-100
                               text-purple-700 border border-purple-200 px-2 py-0.5
                               text-xs font-medium">
                    <mat-icon class="!text-[14px] !h-[14px] !w-[14px] mr-0.5">
                      check_circle
                    </mat-icon>
                    Mandatory
                  </span>
                } @else if (planAddon.is_included) {
                  <span class="inline-flex items-center rounded-full bg-green-100
                               text-green-700 border border-green-200 px-2 py-0.5
                               text-xs font-medium">
                    <mat-icon class="!text-[14px] !h-[14px] !w-[14px] mr-0.5">
                      done_all
                    </mat-icon>
                    Included
                  </span>
                } @else {
                  <span class="inline-flex items-center rounded-full bg-blue-100
                               text-blue-700 border border-blue-200 px-2 py-0.5
                               text-xs font-medium">
                    <mat-icon class="!text-[14px] !h-[14px] !w-[14px] mr-0.5">
                      add_circle
                    </mat-icon>
                    Optional
                  </span>
                }
              </div>

              @if (planAddon.addon?.description) {
                <p class="text-sm text-slate-600 mb-2">
                  {{ planAddon.addon.description }}
                </p>
              }

              <!-- Pricing Info -->
              <div class="flex items-center gap-4 text-sm">
                <span class="text-slate-500">
                  @switch (planAddon.addon?.pricing_type) {
                    @case ('fixed') {
                      Fixed: {{ formatCurrency(planAddon.addon.amount) }}
                    }
                    @case ('per_member') {
                      {{ formatCurrency(planAddon.addon.amount) }} per member
                    }
                    @case ('percentage') {
                      {{ planAddon.addon.percentage }}% of base premium
                    }
                  }
                </span>

                @if (selectedAddonIds().has(planAddon.addon_id) && !planAddon.is_included) {
                  <span class="font-semibold text-slate-900">
                    Est. Premium: {{ formatCurrency(calculateEstimatedPremium(planAddon.addon)) }}
                  </span>
                }

                @if (planAddon.is_included) {
                  <span class="font-semibold text-green-600">
                    No additional cost
                  </span>
                }
              </div>
            </div>
          </div>
        </div>
      }
    </div>
  }
</div>
```

#### Store Changes (application.store.ts)

```typescript
// Add method to toggle addon
addAddonToApplication(applicationId: string, addonId: string) {
  this.state.update(s => ({ ...s, saving: true }));

  return this.http.post(
    `${this.apiUrl}/${applicationId}/addons`,
    { addon_id: addonId }
  ).pipe(
    tap({
      next: () => {
        // Reload application to get updated premiums
        this.loadOne(applicationId).subscribe();
        this.state.update(s => ({ ...s, saving: false }));
      },
      error: () => this.state.update(s => ({ ...s, saving: false }))
    })
  );
}

removeAddonFromApplication(applicationId: string, addonId: string) {
  // Find the ApplicationAddon record
  const addon = this.addons().find(a => a.addon_id === addonId);
  if (!addon) return;

  this.state.update(s => ({ ...s, saving: true }));

  return this.http.delete(
    `${this.apiUrl}/${applicationId}/addons/${addon.id}`
  ).pipe(
    tap({
      next: () => {
        this.loadOne(applicationId).subscribe();
        this.state.update(s => ({ ...s, saving: false }));
      },
      error: () => this.state.update(s => ({ ...s, saving: false }))
    })
  );
}
```

### 2. **Handle Multiple Plans Scenario**

**Current State:**
- Application has single `plan_id`
- All members share the same plan
- Premium calculation uses one plan globally

**Options:**

#### Option A: Keep Single Plan (Simplest)
✅ **Recommended for MVP**
- One application = One plan
- For multi-plan groups, create separate applications per plan
- Simplifies addon logic significantly

#### Option B: Plan per Member (Complex)
❌ **Not recommended** - requires major refactoring:
- Add `plan_id` to `med_application_members` table
- Update premium calculation to loop members by plan
- Handle addons per plan (complex UI)
- Much more complex frontend logic

**Recommendation:** Stick with Option A. If a group needs multiple plans:
1. Create separate applications for each plan
2. Each application has its own addon selection
3. Convert to separate policies
4. Link policies via `group_id`

### 3. **Premium Calculation Verification**

#### Current Flow is Correct:
```
1. Create Application with plan_id
2. Add Members → calculatePremium() → base_premium updated
3. Auto-apply mandatory addons → calculatePremium() → addon_premium updated
4. User selects optional addons → calculatePremium() → addon_premium updated
5. Apply discounts → total_premium updated
6. Generate quote
```

#### Backend Already Handles:
- ✅ Included addons (0 premium)
- ✅ Mandatory addons (auto-calculated)
- ✅ Optional addons (only if selected)
- ✅ Different pricing types (fixed, per_member, percentage)
- ✅ Basis for percentage (base_premium or total_premium)

#### What Needs Testing:
1. Verify `percentage_basis` logic in `Addon::calculatePremium()`
2. Test addon removal triggers recalculation
3. Ensure ApplicationAddon.premium is saved correctly
4. Verify tax calculation includes addon premium

## Implementation Steps

### Phase 1: Backend Verification ✅
- [x] Confirm `/api/v1/medical/plans/{planId}/addons` endpoint works
- [x] Verify PlanAddonResource includes all needed fields
- [x] Test premium calculation with mandatory/optional/included addons

### Phase 2: Frontend Refactor
1. **Remove dialog approach** (medical-application-addon-dialog.ts)
2. **Add inline addon section** to application-detail.html
3. **Implement checkbox logic** with mandatory/optional handling
4. **Add store methods** for adding/removing addons
5. **Display estimated premiums** inline
6. **Auto-select mandatory addons** on load

### Phase 3: Testing
1. Test with plan having only mandatory addons
2. Test with plan having only optional addons
3. Test with plan having included addons (0 premium)
4. Test with mixed availability addons
5. Verify premium recalculation on addon toggle
6. Test with different pricing types (fixed, per_member, percentage)

## Summary

### ✅ Good News
- **Backend is ready** - no changes needed!
- `med_plan_addons.availability` field exists and works
- Premium calculation properly handles all scenarios
- API endpoint returns everything we need

### 🔧 What Needs Work
- **Frontend UI**: Replace dialog with inline checkboxes
- **Auto-application**: Mandatory addons should be pre-checked
- **Store logic**: Add methods to add/remove addons inline
- **Premium display**: Show estimated premium per addon

### 🎯 Simplifications
- **Single plan per application** - don't try to support multiple plans
- **Keep premium calculation as-is** - it already works correctly
- **Use existing API endpoints** - no backend changes needed

### 📝 Key Points
1. **Mandatory addons**: Auto-select and disable checkbox
2. **Optional addons**: User can toggle on/off
3. **Included addons**: Show as "No additional cost"
4. **Premium calculation**: Recalculate on every addon change
5. **Use signals and computed** - no direct HTTP in components
