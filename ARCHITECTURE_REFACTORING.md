# Architecture Refactoring - Proper Signal Store Pattern

## ✅ Completed: Remove HTTP Calls from Components

### 🎯 Problem Identified

The initial implementation had HTTP calls directly in the component:

```typescript
// ❌ BAD - Component making HTTP calls
private loadPlanAddons(planId: string) {
  this.isLoadingPlanAddons.set(true);

  this.store.loadPlanAddons(planId).subscribe({  // Returns Observable
    next: (res) => {
      this.planAddons.set(res.data || []);        // Manual state management
      this.isLoadingPlanAddons.set(false);
    },
    error: () => {
      this.feedback.error('Failed to load plan addons');
      this.isLoadingPlanAddons.set(false);
    }
  });
}
```

**Issues:**
- ❌ Component manages its own loading state (`isLoadingPlanAddons`)
- ❌ Component manages data state (`planAddons`)
- ❌ Store method returns `Observable` instead of managing state
- ❌ Duplicate state management (store AND component)
- ❌ Component has too many responsibilities

---

## ✅ Solution: Signal Store Pattern

### **Store Manages Everything**

```typescript
// ✅ GOOD - Store manages state
interface ApplicationState {
  items: Application[];
  selected: Application | null;
  stats: ApplicationStats | null;
  loading: boolean;
  saving: boolean;
  planAddons: any[];              // ← State in store
  loadingPlanAddons: boolean;     // ← Loading state in store
}

@Injectable({ providedIn: 'root' })
export class ApplicationStore {
  private readonly state = signal<ApplicationState>({
    items: [],
    selected: null,
    stats: null,
    loading: false,
    saving: false,
    planAddons: [],                // ← Initialized in store
    loadingPlanAddons: false,      // ← Initialized in store
  });

  // Expose as computed signals (read-only)
  readonly planAddons = computed(() => this.state().planAddons);
  readonly isLoadingPlanAddons = computed(() => this.state().loadingPlanAddons);

  /**
   * Load plan addons - Updates internal state
   * Returns: void (no Observable)
   */
  loadPlanAddons(planId: string) {
    this.state.update((s) => ({ ...s, loadingPlanAddons: true }));

    this.http.get<ApiResponse<any[]>>(`/api/v1/medical/plans/${planId}/addons`).subscribe({
      next: (res) => {
        this.state.update((s) => ({
          ...s,
          planAddons: res.data || [],
          loadingPlanAddons: false,
        }));
      },
      error: () => {
        this.state.update((s) => ({
          ...s,
          planAddons: [],
          loadingPlanAddons: false,
        }));
      },
    });
  }
}
```

### **Component Just Reads Signals**

```typescript
// ✅ GOOD - Component reads from store only
export class MedicalApplicationDetail {
  readonly store = inject(ApplicationStore);

  // Just point to store signals (no local state)
  readonly planAddons = computed(() => this.store.planAddons());
  readonly isLoadingPlanAddons = computed(() => this.store.isLoadingPlanAddons());

  private loadApplication() {
    this.store.loadOne(this.applicationId()).subscribe({
      next: () => {
        // ... update data sources ...

        // Just call store method - no subscribe needed
        const app = this.application();
        if (app?.plan_id) {
          this.store.loadPlanAddons(app.plan_id);  // ← Fire and forget
        }
      },
    });
  }
}
```

---

## 📊 Before vs After Comparison

### ❌ BEFORE: Anti-Pattern

```
┌─────────────────────────────────────┐
│         Component                   │
│                                     │
│  • Has local signals               │
│  • Makes HTTP calls                 │
│  • Manages loading state            │
│  • Subscribes to observables        │
│  • Updates local state manually     │
│                                     │
│       ↓ calls                       │
│                                     │
│  Store.loadPlanAddons()             │
│    → Returns Observable<T>          │
│    → Component handles response     │
└─────────────────────────────────────┘

Responsibilities:
- Component: 70%
- Store: 30%
```

### ✅ AFTER: Proper Pattern

```
┌─────────────────────────────────────┐
│         Component                   │
│                                     │
│  • Reads store signals              │
│  • Calls store methods              │
│  • Pure presentation logic          │
│  • No HTTP, no subscriptions        │
│                                     │
│       ↓ calls                       │
│                                     │
│         Store                       │
│                                     │
│  • Owns all state (signal)          │
│  • Makes HTTP calls internally      │
│  • Manages loading states           │
│  • Updates state on response        │
│  • Exposes computed signals         │
└─────────────────────────────────────┘

Responsibilities:
- Component: 20% (presentation only)
- Store: 80% (state + data)
```

---

## 🔑 Key Principles

### 1. **Single Source of Truth**
```typescript
// ✅ State lives ONLY in store
private readonly state = signal<ApplicationState>({ ... });

// ❌ NOT in component
readonly planAddons = signal<PlanAddon[]>([]);  // NO!
```

### 2. **Store Methods Don't Return Data**
```typescript
// ✅ GOOD - Void return, updates internal state
loadPlanAddons(planId: string): void {
  // ... update this.state ...
}

// ❌ BAD - Returns Observable
loadPlanAddons(planId: string): Observable<ApiResponse<any[]>> {
  return this.http.get(...);  // NO!
}
```

### 3. **Components Read, Don't Write**
```typescript
// ✅ GOOD - Read-only computed
readonly planAddons = computed(() => this.store.planAddons());

// ❌ BAD - Writable signal in component
readonly planAddons = signal<PlanAddon[]>([]);
```

### 4. **Fire and Forget**
```typescript
// ✅ GOOD - Just call, no subscribe
this.store.loadPlanAddons(planId);

// ❌ BAD - Subscribe in component
this.store.loadPlanAddons(planId).subscribe(res => { ... });
```

---

## 📁 Files Changed

### 1. **ApplicationStore** ([application.store.ts](front-end/projects/libs/medical/data/src/lib/stores/application.store.ts))

**Changes:**
- ✅ Added `planAddons: any[]` to state
- ✅ Added `loadingPlanAddons: boolean` to state
- ✅ Exposed `planAddons` as computed signal
- ✅ Exposed `isLoadingPlanAddons` as computed signal
- ✅ Refactored `loadPlanAddons()` to update internal state (no return)
- ✅ Added `clearPlanAddons()` utility method

**Before:**
```typescript
loadPlanAddons(planId: string) {
  return this.http.get<ApiResponse<any[]>>(`/api/v1/medical/plans/${planId}/addons`);
}
```

**After:**
```typescript
loadPlanAddons(planId: string) {
  this.state.update((s) => ({ ...s, loadingPlanAddons: true }));

  this.http.get<ApiResponse<any[]>>(`/api/v1/medical/plans/${planId}/addons`).subscribe({
    next: (res) => {
      this.state.update((s) => ({
        ...s,
        planAddons: res.data || [],
        loadingPlanAddons: false,
      }));
    },
    error: () => {
      this.state.update((s) => ({ ...s, planAddons: [], loadingPlanAddons: false }));
    },
  });
}
```

---

### 2. **Application Detail Component** ([medical-application-detail.ts](front-end/projects/libs/medical/feature/src/lib/medical-application-detail/medical-application-detail.ts))

**Changes:**
- ✅ Removed local `planAddons` signal
- ✅ Removed local `isLoadingPlanAddons` signal
- ✅ Changed to computed signals pointing to store
- ✅ Removed `loadPlanAddons()` method
- ✅ Simplified `loadApplication()` to just call `store.loadPlanAddons()`

**Before:**
```typescript
export class MedicalApplicationDetail {
  // Local state (BAD)
  readonly planAddons = signal<PlanAddon[]>([]);
  readonly isLoadingPlanAddons = signal<boolean>(false);

  private loadPlanAddons(planId: string) {
    this.isLoadingPlanAddons.set(true);

    this.store.loadPlanAddons(planId).subscribe({
      next: (res) => {
        this.planAddons.set(res.data || []);
        this.isLoadingPlanAddons.set(false);
      },
      error: () => {
        this.feedback.error('Failed to load plan addons');
        this.isLoadingPlanAddons.set(false);
      }
    });
  }
}
```

**After:**
```typescript
export class MedicalApplicationDetail {
  // Read from store (GOOD)
  readonly planAddons = computed(() => this.store.planAddons());
  readonly isLoadingPlanAddons = computed(() => this.store.isLoadingPlanAddons());

  private loadApplication() {
    this.store.loadOne(this.applicationId()).subscribe({
      next: () => {
        // ...

        // Just call store method (no subscribe)
        const app = this.application();
        if (app?.plan_id) {
          this.store.loadPlanAddons(app.plan_id);
        }
      },
    });
  }
}
```

---

## ✅ Benefits of This Pattern

### 1. **Separation of Concerns**
- Store: Data fetching + state management
- Component: UI logic + presentation
- Clear boundaries, easy to understand

### 2. **Single Source of Truth**
- State lives in ONE place (store)
- No synchronization issues
- No duplicate state

### 3. **Testability**
- Store can be tested independently
- Component tests don't need to mock HTTP
- Pure functions easier to test

### 4. **Maintainability**
- Change data source? Edit store only
- Change UI? Edit component only
- Clear responsibilities

### 5. **Reusability**
- Other components can use same store signals
- No duplicate HTTP calls
- Consistent state across app

### 6. **Type Safety**
- Store enforces state shape
- Computed signals are type-safe
- Compile-time checks

---

## 🔍 Pattern Verification Checklist

Use this to verify other components follow the pattern:

### ✅ Store Should Have:
- [ ] Private `state` signal with all data
- [ ] Public computed signals (read-only)
- [ ] Methods that UPDATE state (void return)
- [ ] HTTP calls INSIDE methods, not exposed
- [ ] Loading states in `state` signal

### ✅ Component Should Have:
- [ ] Computed signals pointing to store
- [ ] NO local data signals
- [ ] NO HTTP calls
- [ ] NO manual state updates
- [ ] Just call store methods (fire & forget)

### ❌ Component Should NOT Have:
- [ ] ❌ `signal<T>()` for data
- [ ] ❌ `.subscribe()` in methods (except for dialogs/routing)
- [ ] ❌ `this.http.get/post/...`
- [ ] ❌ `.set()` on data signals
- [ ] ❌ Loading state management

---

## 📝 Example: Other Store Methods

All store methods should follow this pattern:

```typescript
// ✅ GOOD Pattern
addAddon(applicationId: string, addonId: string) {
  this.state.update((s) => ({ ...s, saving: true }));

  this.http.post<ApiResponse<Application>>(`${this.apiUrl}/${applicationId}/addons`, {
    addon_id: addonId,
  }).pipe(
    tap({
      next: (res) => this.updateApplicationInState(applicationId, res.data),
      error: () => this.state.update((s) => ({ ...s, saving: false })),
    })
  );
}

// Usage in component
this.store.addAddon(app.id, addonId).subscribe({
  next: () => this.feedback.success('Added'),
  error: (err) => this.feedback.error(err.message)
});
```

**Note:** Methods that trigger workflows (add/remove/update) can still return Observables so components can show success/error messages. But data-fetching methods (load*) should NOT return Observables.

---

## 🎯 Summary

| Aspect | Before | After |
|--------|--------|-------|
| **State Location** | Component + Store | Store only |
| **HTTP Calls** | Component | Store only |
| **Loading States** | Component | Store only |
| **Component Role** | Data + UI | UI only |
| **Store Methods** | Return Observable | Update state (void) |
| **Reactivity** | Manual .set() | Automatic (signals) |
| **Testability** | Hard | Easy |
| **Reusability** | Low | High |

**Result:** Clean, maintainable, Angular-idiomatic code! 🎉
