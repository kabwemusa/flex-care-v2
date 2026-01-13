# Security & Permissions Implementation Summary

## ✅ What Has Been Implemented

### 1. Backend Security Infrastructure

#### ✅ Permission Middleware (Enhanced)
**File**: `backend/Modules/Medical/Http/Middleware/CheckMedicalPermission.php`

Features:
- System admins automatically bypass all permission checks
- Uses Spatie's `hasPermissionTo()` with guard support
- Returns standardized JSON error responses
- Cached permission checks (Spatie auto-caching)

**Already registered** in `bootstrap/app.php` as `medical.permission`

#### ✅ Audit Trail (Already Active)
**Using**: OwenIt\Auditing package

All models using `Auditable` trait automatically track:
- Who made changes (user_id)
- What changed (old_values, new_values)
- When it changed (created_at)
- What action (created, updated, deleted)

**Active on models**:
- Application
- Policy
- ApplicationMember
- PolicyMember
- Group
- User

#### ✅ New Permissions Added to Seeder
**File**: `backend/database/seeders/PermissionSeeder.php`

Added:
- `medical.groups.import_census` - For census upload functionality
- `medical.groups.bulk_approve` - For bulk approval operations
- `medical.groups.bulk_convert` - For bulk policy conversion

### 2. Frontend Security Infrastructure

#### ✅ AuthService Methods (Already Exist)
**File**: `front-end/projects/libs/core/auth/src/lib/services/auth.service.ts`

Available methods:
```typescript
// Single permission check
authService.isAllowed('medical.schemes.create') // boolean

// Multiple permissions (ANY)
authService.isAllowedAny(['medical.schemes.update', 'medical.schemes.delete'])

// Multiple permissions (ALL)
authService.isAllowedAll(['medical.policies.view', 'medical.policies.update'])

// System admin check
authService.isSystemAdmin() // boolean
```

**Performance**:
- ✅ Permissions loaded once on login
- ✅ Stored in signals (reactive, cached)
- ✅ Stored in localStorage (persist across page reloads)
- ✅ Zero API calls after initial login

#### ✅ Permission Directive (NEW)
**File**: `front-end/projects/libs/shared/src/lib/directives/permission.directive.ts`

Usage:
```html
<!-- Single permission -->
<button *libPermission="'medical.schemes.create'">Create</button>

<!-- Multiple permissions (ANY) -->
<button *libPermission="['medical.schemes.update', 'medical.schemes.delete']; mode: 'any'">Modify</button>

<!-- Multiple permissions (ALL) -->
<div *libPermission="['medical.policies.view', 'medical.policies.update']; mode: 'all'">...</div>
```

Features:
- ✅ Automatically hides/shows elements
- ✅ Subscribes to permission changes
- ✅ Supports single or multiple permissions
- ✅ Supports 'any' or 'all' modes
- ✅ System admins see all elements

**Exported** from `@libs/shared`

---

## 📋 What Needs To Be Done (Implementation Steps)

### Step 1: Run Permission Seeder

```bash
cd backend
php artisan db:seed --class=PermissionSeeder
```

This will add the new permissions to the database.

### Step 2: Add Permission Middleware to Routes

**File**: `backend/Modules/Medical/Routes/api.php`

**Pattern**: Group routes by permission requirement

```php
// Example: Groups Management
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

// Census upload
Route::post('applications/import-census', [ApplicationController::class, 'importCensus'])
    ->middleware('medical.permission:medical.groups.import_census');

Route::post('applications/create-from-census', [ApplicationController::class, 'createFromCensus'])
    ->middleware('medical.permission:medical.groups.import_census');

Route::post('applications/create-multi-plan-from-census', [ApplicationController::class, 'createMultiPlanFromCensus'])
    ->middleware('medical.permission:medical.groups.import_census');
```

**Complete route protection examples provided in**: `SECURITY_PERMISSIONS_GUIDE.md`

### Step 3: Update Frontend Components

**Import the directive and AuthService:**

```typescript
// In component
import { PermissionDirective } from 'shared';
import { AuthService } from '@libs/core/auth';

@Component({
  imports: [
    // ... other imports
    PermissionDirective, // Add this
  ],
})
export class MedicalGroupsList {
  readonly authService = inject(AuthService);

  // Computed permissions (cached by signals)
  readonly canCreate = computed(() =>
    this.authService.isAllowed('medical.groups.create')
  );

  readonly canEdit = computed(() =>
    this.authService.isAllowed('medical.groups.update')
  );

  readonly canDelete = computed(() =>
    this.authService.isAllowed('medical.groups.delete')
  );

  readonly canImportCensus = computed(() =>
    this.authService.isAllowed('medical.groups.import_census')
  );
}
```

**Update template:**

```html
<!-- Hide create button if no permission -->
<button
  mat-flat-button
  color="primary"
  *libPermission="'medical.groups.create'"
  (click)="createGroup()">
  <mat-icon>add</mat-icon>
  Create Group
</button>

<!-- Hide census upload if no permission -->
<button
  mat-flat-button
  *libPermission="'medical.groups.import_census'"
  (click)="uploadCensus(group)">
  <mat-icon>upload_file</mat-icon>
  Upload Census
</button>

<!-- Actions menu - conditional items -->
<button mat-icon-button [matMenuTriggerFor]="menu">
  <mat-icon>more_vert</mat-icon>
</button>
<mat-menu #menu="matMenu">
  <button
    mat-menu-item
    *libPermission="'medical.groups.update'"
    (click)="edit(group)">
    <mat-icon>edit</mat-icon>
    <span>Edit</span>
  </button>

  <button
    mat-menu-item
    *libPermission="'medical.groups.delete'"
    (click)="delete(group)">
    <mat-icon>delete</mat-icon>
    <span>Delete</span>
  </button>

  <button
    mat-menu-item
    *libPermission="'medical.groups.import_census'"
    (click)="uploadCensus(group)">
    <mat-icon>upload_file</mat-icon>
    <span>Upload Census</span>
  </button>
</mat-menu>
```

### Step 4: Assign Permissions to Roles

You need to assign permissions to roles in your application (via admin panel or seeder):

```php
// Example: Create roles with permissions
use Spatie\Permission\Models\Role;

// Medical Admin role
$medicalAdmin = Role::create(['name' => 'Medical Administrator', 'guard_name' => 'medical']);
$medicalAdmin->givePermissionTo([
    'medical.schemes.view',
    'medical.schemes.create',
    'medical.schemes.update',
    'medical.schemes.delete',
    'medical.schemes.activate',
    'medical.plans.view',
    'medical.plans.create',
    'medical.plans.update',
    'medical.plans.delete',
    'medical.applications.view',
    'medical.applications.create',
    'medical.applications.update',
    'medical.applications.delete',
    'medical.groups.view',
    'medical.groups.create',
    'medical.groups.update',
    'medical.groups.delete',
    'medical.groups.import_census',
    // ... all permissions
]);

// Medical Agent role (limited permissions)
$medicalAgent = Role::create(['name' => 'Medical Agent', 'guard_name' => 'medical']);
$medicalAgent->givePermissionTo([
    'medical.schemes.view',
    'medical.plans.view',
    'medical.applications.view',
    'medical.applications.create',
    'medical.applications.update',
    'medical.applications.quote',
    'medical.groups.view',
    'medical.groups.create',
    'medical.groups.import_census',
    // Limited permissions for agents
]);

// Medical Underwriter role
$underwriter = Role::create(['name' => 'Medical Underwriter', 'guard_name' => 'medical']);
$underwriter->givePermissionTo([
    'medical.applications.view',
    'medical.underwriting.view',
    'medical.underwriting.assess',
    'medical.underwriting.approve',
    'medical.underwriting.reject',
    'medical.underwriting.add_loading',
    'medical.underwriting.add_exclusion',
]);

// Assign role to user
$user->assignRole('Medical Administrator');
```

---

## 🎯 Components That Need Permission Updates

### High Priority (User-Facing CRUD):

1. **medical-groups-list**
   - Permissions: view, create, update, delete, import_census

2. **medical-schemes-list**
   - Permissions: view, create, update, delete, activate

3. **medical-plans-list**
   - Permissions: view, create, update, delete, configure

4. **medical-applications-list**
   - Permissions: view, create, update, delete, submit, quote

5. **medical-policies-list**
   - Permissions: view, create, update, renew, suspend, cancel

6. **medical-group-detail**
   - Permissions: view, update, import_census, bulk_approve

### Medium Priority (Configuration):

7. **medical-rate-card-list**
8. **medical-addon-list**
9. **medical-discount-list**
10. **medical-loading-rule-list**

### Dialogs That Need Permission Checks:

- **medical-group-dialog** (create/edit group)
- **medical-census-upload-dialog** (import_census permission)
- **medical-application-dialog** (create/edit application)
- **medical-underwriting-dialog** (underwriting permissions)

---

## 🔐 Permission Naming Reference

```
medical.{resource}.{action}

Resources:
- schemes
- plans
- rate_cards
- addons
- applications
- underwriting
- policies
- members
- groups
- premium
- reports

Actions:
- view (GET list/detail)
- create (POST new)
- update (PUT/PATCH existing)
- delete (DELETE)
- activate/deactivate (special actions)
- submit, quote (application workflow)
- approve, reject (underwriting)
- renew, suspend, cancel (policy lifecycle)
- import_census, bulk_approve (group actions)
```

---

## ⚡ Performance Optimization

### Backend (Already Optimized):
✅ Spatie Permission package caches permissions automatically
✅ System admins skip permission checks
✅ Single DB query per request (or zero if cached)

### Frontend (Already Optimized):
✅ Permissions loaded once on login
✅ Stored in signals (reactive, auto-cached)
✅ No API calls for permission checks
✅ Use computed signals for component permissions

---

## 🧪 Testing Checklist

### Backend Testing:

```bash
# Test as non-admin without permission
curl -H "Authorization: Bearer {token}" http://localhost/api/v1/medical/schemes
# Should return 403 Forbidden

# Test as system admin
curl -H "Authorization: Bearer {admin_token}" http://localhost/api/v1/medical/schemes
# Should return 200 OK

# Test with correct permission
# 1. Assign permission to user
# 2. Login to get token
# 3. Test endpoint
# Should return 200 OK
```

### Frontend Testing:

1. Login as system admin → See all buttons
2. Login as user with limited permissions → See only authorized buttons
3. Login as user with no permissions → See no action buttons
4. Check browser console for permission checks

---

## 📚 Documentation Files Created

1. **SECURITY_PERMISSIONS_GUIDE.md** - Complete implementation guide with examples
2. **SECURITY_IMPLEMENTATION_SUMMARY.md** - This file (quick reference)
3. **Permission Directive** - `front-end/projects/libs/shared/src/lib/directives/permission.directive.ts`
4. **Enhanced Middleware** - `backend/Modules/Medical/Http/Middleware/CheckMedicalPermission.php`

---

## 🚀 Quick Start Checklist

- [ ] Run permission seeder: `php artisan db:seed --class=PermissionSeeder`
- [ ] Update medical routes with permission middleware (see SECURITY_PERMISSIONS_GUIDE.md)
- [ ] Create roles and assign permissions
- [ ] Update frontend components (start with medical-groups-list)
- [ ] Import PermissionDirective in components
- [ ] Add permission checks to templates
- [ ] Test with different user roles
- [ ] Verify audit logs are working

---

## 💡 Best Practices

### Backend:
1. Always group routes by permission to reduce repetition
2. Use transactions for multi-step operations
3. Let Spatie handle permission caching (don't manually cache)
4. System admins should bypass permission checks

### Frontend:
1. Use computed signals for permissions (cached automatically)
2. Use `*libPermission` directive for hiding elements
3. Use `[disabled]="!canEdit()"` for disabling instead of hiding
4. Don't call `authService.isAllowed()` directly in templates (use computed signals)
5. System admins should see all UI elements

### Security:
1. Never hardcode permissions in controllers
2. Always validate permissions on the backend (frontend is for UX only)
3. Use audit trail for sensitive operations
4. Review audit logs regularly

---

## 🔥 Common Patterns

### Backend Route Pattern:
```php
// Read operations (view permission)
Route::middleware(['medical.permission:medical.{resource}.view'])->group(function () {
    Route::get('{resource}', [..., 'index']);
    Route::get('{resource}/{id}', [..., 'show']);
});

// Write operations (separate permissions)
Route::post('{resource}', [..., 'store'])
    ->middleware('medical.permission:medical.{resource}.create');
Route::put('{resource}/{id}', [..., 'update'])
    ->middleware('medical.permission:medical.{resource}.update');
Route::delete('{resource}/{id}', [..., 'destroy'])
    ->middleware('medical.permission:medical.{resource}.delete');
```

### Frontend Component Pattern:
```typescript
// Component
readonly canView = computed(() => this.authService.isAllowed('medical.{resource}.view'));
readonly canCreate = computed(() => this.authService.isAllowed('medical.{resource}.create'));
readonly canEdit = computed(() => this.authService.isAllowed('medical.{resource}.update'));
readonly canDelete = computed(() => this.authService.isAllowed('medical.{resource}.delete'));
```

```html
<!-- Template -->
<button *libPermission="'medical.{resource}.create'" (click)="create()">Create</button>
<button *libPermission="'medical.{resource}.update'" (click)="edit()">Edit</button>
<button *libPermission="'medical.{resource}.delete'" (click)="delete()">Delete</button>
```

---

## Questions?

See `SECURITY_PERMISSIONS_GUIDE.md` for detailed examples and full implementation guide.
