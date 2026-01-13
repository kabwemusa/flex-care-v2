# Security & Permissions Implementation Guide

## Overview
This guide shows how to implement proper permission checks across the entire system for optimal security without performance degradation.

## Backend Implementation

### 1. Permission Middleware (Already Created)

**File**: `backend/Modules/Medical/Http/Middleware/CheckMedicalPermission.php`

Features:
- ✅ System admins bypass all checks
- ✅ Uses cached permissions (Spatie automatically caches)
- ✅ Returns standardized error responses
- ✅ Supports medical guard

### 2. Adding Permissions to Routes

**Pattern**: Add `medical.permission:{permission}` middleware to routes

```php
// Example: Schemes
Route::get('schemes', [SchemeController::class, 'index'])
    ->middleware('medical.permission:medical.schemes.view');

Route::post('schemes', [SchemeController::class, 'store'])
    ->middleware('medical.permission:medical.schemes.create');

Route::put('schemes/{id}', [SchemeController::class, 'update'])
    ->middleware('medical.permission:medical.schemes.update');

Route::delete('schemes/{id}', [SchemeController::class, 'destroy'])
    ->middleware('medical.permission:medical.schemes.delete');

Route::post('schemes/{id}/activate', [SchemeController::class, 'activate'])
    ->middleware('medical.permission:medical.schemes.activate');
```

### 3. Optimized Route Grouping

**Use route groups to reduce repetition:**

```php
// Group routes by permission
Route::middleware(['medical.permission:medical.schemes.view'])->group(function () {
    Route::get('schemes', [SchemeController::class, 'index']);
    Route::get('schemes/{id}', [SchemeController::class, 'show']);
    Route::get('schemes/dropdown', [SchemeController::class, 'dropdown']);
});

Route::middleware(['medical.permission:medical.schemes.create'])->group(function () {
    Route::post('schemes', [SchemeController::class, 'store']);
    Route::post('schemes/{id}/clone', [SchemeController::class, 'clone']);
});

Route::middleware(['medical.permission:medical.schemes.update'])->group(function () {
    Route::put('schemes/{id}', [SchemeController::class, 'update']);
    Route::patch('schemes/{id}', [SchemeController::class, 'update']);
    Route::post('schemes/{id}/activate', [SchemeController::class, 'activate']);
});

Route::middleware(['medical.permission:medical.schemes.delete'])->group(function () {
    Route::delete('schemes/{id}', [SchemeController::class, 'destroy']);
});
```

### 4. Complete Permission Map for Medical Module

```php
// =========================================================================
// SCHEMES
// =========================================================================
Route::middleware(['medical.permission:medical.schemes.view'])->group(function () {
    Route::get('schemes', [SchemeController::class, 'index']);
    Route::get('schemes/{id}', [SchemeController::class, 'show']);
    Route::get('schemes/dropdown', [SchemeController::class, 'dropdown']);
});

Route::post('schemes', [SchemeController::class, 'store'])
    ->middleware('medical.permission:medical.schemes.create');

Route::put('schemes/{id}', [SchemeController::class, 'update'])
    ->middleware('medical.permission:medical.schemes.update');

Route::delete('schemes/{id}', [SchemeController::class, 'destroy'])
    ->middleware('medical.permission:medical.schemes.delete');

Route::post('schemes/{id}/activate', [SchemeController::class, 'activate'])
    ->middleware('medical.permission:medical.schemes.activate');

// =========================================================================
// APPLICATIONS
// =========================================================================
Route::middleware(['medical.permission:medical.applications.view'])->group(function () {
    Route::get('applications', [ApplicationController::class, 'index']);
    Route::get('applications/{id}', [ApplicationController::class, 'show']);
});

Route::middleware(['medical.permission:medical.applications.create'])->group(function () {
    Route::post('applications', [ApplicationController::class, 'store']);
    Route::post('applications/import-census', [ApplicationController::class, 'importCensus'])
        ->middleware('medical.permission:medical.groups.import_census');
    Route::post('applications/create-from-census', [ApplicationController::class, 'createFromCensus']);
    Route::post('applications/create-multi-plan-from-census', [ApplicationController::class, 'createMultiPlanFromCensus']);
});

Route::middleware(['medical.permission:medical.applications.update'])->group(function () {
    Route::put('applications/{id}', [ApplicationController::class, 'update']);
    Route::patch('applications/{id}', [ApplicationController::class, 'update']);
    Route::post('applications/{id}/calculate-premium', [ApplicationController::class, 'calculatePremium']);
    Route::post('applications/{id}/members', [ApplicationController::class, 'addMember']);
    Route::put('applications/{appId}/members/{memberId}', [ApplicationController::class, 'updateMember']);
});

Route::middleware(['medical.permission:medical.applications.delete'])->group(function () {
    Route::delete('applications/{id}', [ApplicationController::class, 'destroy']);
    Route::delete('applications/{appId}/members/{memberId}', [ApplicationController::class, 'removeMember']);
});

Route::post('applications/{id}/submit', [ApplicationController::class, 'submit'])
    ->middleware('medical.permission:medical.applications.submit');

Route::post('applications/{id}/quote', [ApplicationController::class, 'markAsQuoted'])
    ->middleware('medical.permission:medical.applications.quote');

// =========================================================================
// UNDERWRITING
// =========================================================================
Route::middleware(['medical.permission:medical.underwriting.view'])->group(function () {
    Route::get('applications/{id}/underwriting', [ApplicationController::class, 'underwritingDetails']);
});

Route::post('applications/{id}/start-underwriting', [ApplicationController::class, 'startUnderwriting'])
    ->middleware('medical.permission:medical.underwriting.assess');

Route::post('applications/{id}/approve', [ApplicationController::class, 'approve'])
    ->middleware('medical.permission:medical.underwriting.approve');

Route::post('applications/{id}/decline', [ApplicationController::class, 'decline'])
    ->middleware('medical.permission:medical.underwriting.reject');

Route::post('applications/{appId}/members/{memberId}/underwrite', [ApplicationController::class, 'underwriteMember'])
    ->middleware('medical.permission:medical.underwriting.assess');

// =========================================================================
// POLICIES
// =========================================================================
Route::middleware(['medical.permission:medical.policies.view'])->group(function () {
    Route::get('policies', [PolicyController::class, 'index']);
    Route::get('policies/{id}', [PolicyController::class, 'show']);
});

Route::post('applications/{id}/convert', [ApplicationController::class, 'convert'])
    ->middleware('medical.permission:medical.policies.create');

Route::put('policies/{id}', [PolicyController::class, 'update'])
    ->middleware('medical.permission:medical.policies.update');

Route::post('policies/{id}/renew', [PolicyController::class, 'renew'])
    ->middleware('medical.permission:medical.policies.renew');

Route::post('policies/{id}/suspend', [PolicyController::class, 'suspend'])
    ->middleware('medical.permission:medical.policies.suspend');

Route::post('policies/{id}/cancel', [PolicyController::class, 'cancel'])
    ->middleware('medical.permission:medical.policies.cancel');

// =========================================================================
// GROUPS
// =========================================================================
Route::middleware(['medical.permission:medical.groups.view'])->group(function () {
    Route::get('groups', [GroupController::class, 'index']);
    Route::get('groups/{id}', [GroupController::class, 'show']);
});

Route::post('groups', [GroupController::class, 'store'])
    ->middleware('medical.permission:medical.groups.create');

Route::put('groups/{id}', [GroupController::class, 'update'])
    ->middleware('medical.permission:medical.groups.update');

Route::delete('groups/{id}', [GroupController::class, 'destroy'])
    ->middleware('medical.permission:medical.groups.delete');
```

### 5. Audit Trail Configuration

**Already Implemented**: The system uses `OwenIt\Auditing\Auditable` trait.

**Enable auditing on models that need tracking:**

```php
use OwenIt\Auditing\Contracts\Auditable;

class Application extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    // Specify which attributes to audit
    protected $auditInclude = [
        'status',
        'total_premium',
        'approved_by',
        'approved_at',
    ];

    // Exclude sensitive data from audit
    protected $auditExclude = [];

    // Strict audit mode (fails transaction if audit fails)
    protected $auditStrict = true;
}
```

**Audit critical operations are already tracked automatically:**
- ✅ All `create`, `update`, `delete` operations
- ✅ User who made the change
- ✅ Timestamp of change
- ✅ Old and new values

**View audit logs:**
```php
// Get all audits for a specific application
$application = Application::find($id);
$audits = $application->audits;

// Get user who made changes
foreach ($audits as $audit) {
    $user = $audit->user; // User model
    $event = $audit->event; // created, updated, deleted
    $oldValues = $audit->old_values;
    $newValues = $audit->new_values;
    $changedAt = $audit->created_at;
}
```

---

## Frontend Implementation

### 1. AuthService Methods (Already Implemented)

```typescript
// In your components, inject AuthService
readonly authService = inject(AuthService);

// Check single permission
canCreate = this.authService.isAllowed('medical.schemes.create');
canEdit = this.authService.isAllowed('medical.schemes.update');
canDelete = this.authService.isAllowed('medical.schemes.delete');

// Check multiple permissions (ANY)
canModify = this.authService.isAllowedAny([
  'medical.schemes.update',
  'medical.schemes.delete'
]);

// Check multiple permissions (ALL)
canFullAccess = this.authService.isAllowedAll([
  'medical.schemes.view',
  'medical.schemes.create',
  'medical.schemes.update',
  'medical.schemes.delete'
]);

// System admins always return true
```

### 2. Permission Directive (Create This)

**File**: `front-end/projects/libs/shared/src/lib/directives/permission.directive.ts`

```typescript
import { Directive, Input, TemplateRef, ViewContainerRef, inject, OnInit } from '@angular/core';
import { AuthService } from '@libs/core/auth';

@Directive({
  selector: '[libPermission]',
  standalone: true,
})
export class PermissionDirective implements OnInit {
  private authService = inject(AuthService);
  private templateRef = inject(TemplateRef<any>);
  private viewContainer = inject(ViewContainerRef);

  @Input('libPermission') permission!: string | string[];
  @Input('libPermissionMode') mode: 'any' | 'all' = 'all';

  ngOnInit() {
    this.updateView();
  }

  private updateView() {
    const hasPermission = this.checkPermission();

    if (hasPermission) {
      this.viewContainer.createEmbeddedView(this.templateRef);
    } else {
      this.viewContainer.clear();
    }
  }

  private checkPermission(): boolean {
    if (Array.isArray(this.permission)) {
      return this.mode === 'any'
        ? this.authService.isAllowedAny(this.permission)
        : this.authService.isAllowedAll(this.permission);
    }
    return this.authService.isAllowed(this.permission);
  }
}
```

### 3. Using Permission Directive in Components

```html
<!-- Hide button if no permission -->
<button
  mat-flat-button
  *libPermission="'medical.schemes.create'"
  (click)="createScheme()">
  Create Scheme
</button>

<!-- Multiple permissions (ANY) -->
<button
  mat-flat-button
  *libPermission="['medical.schemes.update', 'medical.schemes.delete']; mode: 'any'"
  (click)="modify()">
  Modify
</button>

<!-- Multiple permissions (ALL) -->
<div *libPermission="['medical.policies.view', 'medical.policies.update']; mode: 'all'">
  <h3>Policy Management</h3>
  <!-- Content here -->
</div>

<!-- Census Upload (specific to groups) -->
<button
  mat-flat-button
  *libPermission="['medical.groups.create', 'medical.groups.import_census']; mode: 'all'"
  (click)="uploadCensus()">
  Upload Census
</button>
```

### 4. Disable Buttons (Instead of Hiding)

Sometimes you want to show disabled buttons:

```typescript
// In component
readonly canEdit = computed(() =>
  this.authService.isAllowed('medical.schemes.update')
);

readonly canDelete = computed(() =>
  this.authService.isAllowed('medical.schemes.delete')
);
```

```html
<!-- Template -->
<button
  mat-flat-button
  [disabled]="!canEdit()"
  (click)="edit()">
  Edit
</button>

<button
  mat-icon-button
  [disabled]="!canDelete()"
  (click)="delete()">
  <mat-icon>delete</mat-icon>
</button>
```

### 5. Real-World Component Example

```typescript
// medical-schemes-list.component.ts
export class MedicalSchemesListComponent {
  readonly authService = inject(AuthService);
  readonly schemeStore = inject(SchemeListStore);
  readonly dialog = inject(MatDialog);

  // Computed permissions (cached automatically by signals)
  readonly canView = computed(() =>
    this.authService.isAllowed('medical.schemes.view')
  );

  readonly canCreate = computed(() =>
    this.authService.isAllowed('medical.schemes.create')
  );

  readonly canEdit = computed(() =>
    this.authService.isAllowed('medical.schemes.update')
  );

  readonly canDelete = computed(() =>
    this.authService.isAllowed('medical.schemes.delete')
  );

  readonly canActivate = computed(() =>
    this.authService.isAllowed('medical.schemes.activate')
  );

  createScheme() {
    // This will only be called if user has permission
    // (button is hidden/disabled in template)
    this.dialog.open(MedicalSchemeDialog, {
      data: { mode: 'create' }
    });
  }

  editScheme(scheme: Scheme) {
    this.dialog.open(MedicalSchemeDialog, {
      data: { mode: 'edit', scheme }
    });
  }

  deleteScheme(scheme: Scheme) {
    // Confirm before deleting
    this.feedback.confirm('Are you sure you want to delete this scheme?')
      .subscribe(confirmed => {
        if (confirmed) {
          this.schemeStore.delete(scheme.id).subscribe({
            next: () => this.feedback.success('Scheme deleted'),
            error: (err) => this.feedback.error(err.message)
          });
        }
      });
  }
}
```

```html
<!-- medical-schemes-list.component.html -->
<div class="schemes-list">
  <div class="header">
    <h2>Medical Schemes</h2>

    <!-- Create button - hidden if no permission -->
    <button
      mat-flat-button
      color="primary"
      *libPermission="'medical.schemes.create'"
      (click)="createScheme()">
      <mat-icon>add</mat-icon>
      Create Scheme
    </button>
  </div>

  <table mat-table [dataSource]="schemeStore.schemes()">
    <!-- Name column -->
    <ng-container matColumnDef="name">
      <th mat-header-cell *matHeaderCellDef>Name</th>
      <td mat-cell *matCellDef="let scheme">{{ scheme.name }}</td>
    </ng-container>

    <!-- Actions column -->
    <ng-container matColumnDef="actions">
      <th mat-header-cell *matHeaderCellDef>Actions</th>
      <td mat-cell *matCellDef="let scheme">
        <!-- Edit button -->
        <button
          mat-icon-button
          *libPermission="'medical.schemes.update'"
          (click)="editScheme(scheme)">
          <mat-icon>edit</mat-icon>
        </button>

        <!-- Delete button -->
        <button
          mat-icon-button
          color="warn"
          *libPermission="'medical.schemes.delete'"
          (click)="deleteScheme(scheme)">
          <mat-icon>delete</mat-icon>
        </button>

        <!-- Activate/Deactivate button -->
        <button
          mat-icon-button
          *libPermission="'medical.schemes.activate'"
          (click)="toggleActive(scheme)">
          <mat-icon>{{ scheme.is_active ? 'toggle_on' : 'toggle_off' }}</mat-icon>
        </button>
      </td>
    </ng-container>

    <tr mat-header-row *matHeaderRowDef="displayedColumns"></tr>
    <tr mat-row *matRowDef="let row; columns: displayedColumns;"></tr>
  </table>
</div>
```

---

## Performance Optimization

### 1. Backend (Spatie Permission Caching)

**Already optimized!** Spatie Permission package caches permissions automatically:

- ✅ Permissions cached on first load
- ✅ Cache cleared only when permissions change
- ✅ Single DB query per request (or zero if cached)
- ✅ System admins skip permission checks entirely

### 2. Frontend (Signal-Based Caching)

```typescript
// ✅ GOOD: Use computed signals (cached automatically)
readonly canEdit = computed(() =>
  this.authService.isAllowed('medical.schemes.update')
);

// ❌ BAD: Direct method calls in template (runs on every change detection)
<!-- Template -->
<button [disabled]="authService.isAllowed('medical.schemes.update')">
```

### 3. Permissions Loaded Once on Login

The AuthService loads all permissions once during login and stores them:
- ✅ Stored in signals (reactive)
- ✅ Stored in localStorage (persists across page reloads)
- ✅ No API calls for permission checks after login

---

## Permission Naming Convention

**Format**: `{module}.{resource}.{action}`

**Examples**:
- `medical.schemes.view`
- `medical.schemes.create`
- `medical.schemes.update`
- `medical.schemes.delete`
- `medical.schemes.activate`
- `medical.applications.submit`
- `medical.applications.quote`
- `medical.underwriting.approve`
- `medical.underwriting.reject`
- `medical.groups.import_census`
- `medical.groups.bulk_approve`
- `medical.policies.renew`
- `medical.policies.cancel`

---

## Complete Permissions List

```php
// SCHEMES
'medical.schemes.view'
'medical.schemes.create'
'medical.schemes.update'
'medical.schemes.delete'
'medical.schemes.activate'

// PLANS
'medical.plans.view'
'medical.plans.create'
'medical.plans.update'
'medical.plans.delete'
'medical.plans.configure'

// APPLICATIONS
'medical.applications.view'
'medical.applications.create'
'medical.applications.update'
'medical.applications.delete'
'medical.applications.submit'
'medical.applications.quote'

// UNDERWRITING
'medical.underwriting.view'
'medical.underwriting.assess'
'medical.underwriting.approve'
'medical.underwriting.reject'
'medical.underwriting.add_loading'
'medical.underwriting.add_exclusion'

// POLICIES
'medical.policies.view'
'medical.policies.create'
'medical.policies.update'
'medical.policies.renew'
'medical.policies.suspend'
'medical.policies.cancel'
'medical.policies.reinstate'

// GROUPS
'medical.groups.view'
'medical.groups.create'
'medical.groups.update'
'medical.groups.delete'
'medical.groups.import_census'
'medical.groups.bulk_approve'
'medical.groups.bulk_convert'

// MEMBERS
'medical.members.view'
'medical.members.add'
'medical.members.update'
'medical.members.remove'
'medical.members.suspend'
'medical.members.exit'

// PREMIUM
'medical.premium.view'
'medical.premium.calculate'
'medical.premium.override'

// REPORTS
'medical.reports.view'
'medical.reports.export'
```

---

## Testing Checklist

### Backend:
- [ ] Non-admin user cannot access protected routes without permission
- [ ] System admin can access all routes
- [ ] Permission middleware returns 403 for unauthorized access
- [ ] Audit logs are created for create/update/delete operations

### Frontend:
- [ ] Buttons are hidden/disabled based on permissions
- [ ] Permission directive works with single permission
- [ ] Permission directive works with multiple permissions (any/all modes)
- [ ] System admin sees all buttons
- [ ] Regular user sees only authorized buttons

---

## Next Steps

1. ✅ Run permission seeder: `php artisan db:seed --class=PermissionSeeder`
2. ✅ Create Permission Directive in frontend
3. ✅ Update medical routes with permission middleware
4. ✅ Update all medical components with permission checks
5. ✅ Test with different user roles
6. ✅ Verify audit logs are working
