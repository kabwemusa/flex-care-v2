# Multi-Plan Group Support

## Overview

The system now supports **multiple plans per corporate group**, allowing different employee tiers (C-Suite, Management, Staff) to have different plan coverage, and enabling member plan upgrades/downgrades.

## Architecture

### Key Concepts

1. **Multi-Application per Group**: A single group can have multiple applications, one per plan tier
2. **Plan Assignment Rules**: Members are assigned to plans based on:
   - `salary_band` (Executive, Senior, Mid, Junior)
   - `department` (Sales, IT, Operations, etc.)
   - `job_title` (CEO, Manager, Engineer, etc.)
3. **Plan Migration**: Members can move between plans (upgrade/downgrade) with pro-rata calculations

## Database Structure

### Existing Fields (No Migration Needed)

The `med_application_members` table already has:
- `salary_band` (VARCHAR) - For plan assignment
- `department` (VARCHAR) - Alternative assignment criteria
- `job_title` (VARCHAR) - Additional assignment criteria
- `employee_number` (VARCHAR) - Unique identifier

### No Schema Changes Required! ✅

The existing schema fully supports multi-plan groups.

## API Endpoints

### 1. Create Multi-Plan Applications from Census

**POST** `/api/v1/medical/applications/create-multi-plan-from-census`

**Request:**
```json
{
  "import_key": "census_import_...",
  "group_id": "uuid",
  "scheme_id": "uuid",
  "rate_card_id": "uuid",
  "inception_date": "2026-02-01",
  "billing_frequency": "monthly",
  "plan_mapping": {
    "Executive": "plan_id_premium",
    "Senior": "plan_id_gold",
    "Mid": "plan_id_silver",
    "Junior": "plan_id_bronze"
  },
  "mapping_type": "salary_band"  // or "department" or "job_title"
}
```

**Response:**
```json
{
  "success": true,
  "message": "3 applications created from census with 150 members",
  "data": {
    "applications": [
      {
        "application_id": "uuid",
        "plan_id": "plan_id_premium",
        "plan_name": "Executive Plan",
        "member_count": 15
      },
      {
        "application_id": "uuid",
        "plan_id": "plan_id_gold",
        "plan_name": "Gold Plan",
        "member_count": 45
      },
      {
        "application_id": "uuid",
        "plan_id": "plan_id_bronze",
        "plan_name": "Bronze Plan",
        "member_count": 90
      }
    ],
    "total_members": 150,
    "plans_used": 3
  }
}
```

### 2. Move Member to Different Plan

**POST** `/api/v1/medical/members/{memberId}/change-plan`

**Request:**
```json
{
  "new_plan_id": "uuid",
  "effective_date": "2026-03-01",
  "reason": "promotion"  // promotion, demotion, lateral_move, requested
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "member_id": "uuid",
    "old_plan_id": "uuid",
    "old_plan_name": "Silver Plan",
    "new_plan_id": "uuid",
    "new_plan_name": "Gold Plan",
    "effective_date": "2026-03-01",
    "pro_rata_refund": 850.00,
    "pro_rata_premium": 1250.00,
    "premium_difference": 400.00,
    "reason": "promotion"
  }
}
```

### 3. Get Group Plan Distribution

**GET** `/api/v1/medical/groups/{groupId}/plan-distribution`

**Response:**
```json
{
  "group_id": "uuid",
  "group_name": "ABC Corporation",
  "distribution": {
    "applications": {
      "Executive Plan": {
        "plan_id": "uuid",
        "plan_name": "Executive Plan",
        "member_count": 15,
        "total_premium": 25000.00
      },
      "Gold Plan": {
        "plan_id": "uuid",
        "plan_name": "Gold Plan",
        "member_count": 45,
        "total_premium": 52500.00
      }
    },
    "policies": {
      "Executive Plan": {
        "plan_id": "uuid",
        "plan_name": "Executive Plan",
        "member_count": 12,
        "total_premium": 20000.00
      }
    }
  },
  "total_applications": 3,
  "total_policies": 2
}
```

## Use Cases

### Use Case 1: C-Suite with Premium Plan

**Scenario**: Company has 3 tiers of employees:
- C-Suite (15 people) → Premium Plan
- Management (45 people) → Gold Plan
- Staff (90 people) → Bronze Plan

**Implementation**:

1. **Census File** includes `salary_band`:
```csv
first_name,last_name,date_of_birth,gender,email,employee_number,salary_band,department
John,Doe,1975-05-15,M,john.doe@abc.com,EMP001,Executive,Executive Office
Jane,Smith,1980-03-20,F,jane.smith@abc.com,EMP002,Senior,IT
Bob,Johnson,1990-08-10,M,bob.johnson@abc.com,EMP003,Mid,Operations
```

2. **Upload Census** with plan mapping:
```javascript
const planMapping = {
  Executive: premiumPlanId,
  Senior: goldPlanId,
  Mid: silverPlanId,
  Junior: bronzePlanId
};

await applicationStore.createMultiPlanFromCensus({
  import_key: censusImportKey,
  group_id: groupId,
  scheme_id: schemeId,
  rate_card_id: rateCardId,
  inception_date: '2026-02-01',
  billing_frequency: 'monthly',
  plan_mapping: planMapping,
  mapping_type: 'salary_band'
});
```

3. **Result**: 3 applications created automatically, one per plan tier

### Use Case 2: Promotion/Plan Upgrade

**Scenario**: Employee promoted from Mid-level to Senior, needs plan upgrade

**Implementation**:
```javascript
await memberStore.changePlan(memberId, {
  new_plan_id: goldPlanId,
  effective_date: '2026-03-01',
  reason: 'promotion'
});
```

**System Calculates**:
- Pro-rata refund for remaining period on old plan
- Pro-rata premium for new plan
- Premium difference (additional charge or credit)

### Use Case 3: Department-Based Plans

**Scenario**: Different plans by department:
- Sales → Platinum (high travel coverage)
- IT → Gold (tech-related benefits)
- Operations → Silver (standard coverage)

**Implementation**:
```javascript
const planMapping = {
  Sales: platinumPlanId,
  IT: goldPlanId,
  Operations: silverPlanId
};

await applicationStore.createMultiPlanFromCensus({
  ...censusData,
  plan_mapping: planMapping,
  mapping_type: 'department'
});
```

## Frontend Integration

### Enhanced Census Upload Dialog

Add plan mapping step:

```typescript
// Step 1: Upload Census
// Step 2: Preview Members
// Step 3: Configure Plans (NEW)
// Step 4: Create Applications

interface PlanMappingConfig {
  mapping_type: 'salary_band' | 'department' | 'job_title';
  mappings: Record<string, string>; // value => plan_id
  default_plan_id?: string; // For unmapped members
}
```

### Member Plan Management Component

```typescript
// Show current plan with upgrade/downgrade options
interface MemberPlanInfo {
  current_plan: Plan;
  available_plans: Plan[];
  can_upgrade: boolean;
  can_downgrade: boolean;
  upgrade_cost: number;
  downgrade_refund: number;
}
```

## Business Rules

### Plan Assignment

1. **Priority Order**:
   - If `salary_band` exists and mapped → use it
   - Else if `department` exists and mapped → use it
   - Else if `job_title` exists and mapped → use it
   - Else use `default_plan_id`

2. **Validation**:
   - All mapped plans must exist and be active
   - Mapping type must match available data in census
   - At least one plan must be specified

### Plan Changes

1. **Timing**:
   - Effective date must be >= today
   - Effective date must be <= policy expiry - 30 days
   - Cannot change plan for terminated members

2. **Financial**:
   - Pro-rata calculations based on days remaining
   - Refund issued for old plan
   - New premium charged for new plan
   - Difference = additional payment or credit

3. **Workflow**:
   - Application members: Move between applications
   - Policy members: Create/join new policy, terminate old coverage

## Example Workflows

### Workflow 1: Initial Group Setup with Multi-Plan

```
1. Admin creates corporate group "ABC Corp"
2. Admin uploads census with salary_band column
3. System detects 3 salary bands: Executive, Senior, Mid
4. Admin maps:
   - Executive → Premium Plan ($500/month)
   - Senior → Gold Plan ($300/month)
   - Mid → Silver Plan ($150/month)
5. System creates 3 applications automatically
6. Each application goes through underwriting separately
7. After approval, convert each to policy
8. Result: ABC Corp has 3 active policies, one per tier
```

### Workflow 2: Mid-Year Promotion

```
1. Employee promoted from Mid to Senior (Mar 1)
2. HR requests plan change via member detail page
3. System calculates:
   - Inception: Jan 1, Expiry: Dec 31 (365 days)
   - Days remaining: 306 days (Mar 1 - Dec 31)
   - Old premium: $150/month = $1800/year
   - New premium: $300/month = $3600/year
   - Pro-rata refund: ($1800 / 365) * 306 = $1509
   - Pro-rata charge: ($3600 / 365) * 306 = $3018
   - Additional cost: $3018 - $1509 = $1509
4. System moves member to Gold Plan policy
5. Billing adjusted automatically
```

## Testing Scenarios

### Test 1: Multi-Plan Census Upload
- Upload census with 150 members across 3 salary bands
- Verify 3 applications created
- Verify member counts correct per application
- Verify premiums calculated correctly

### Test 2: Plan Upgrade
- Select member on Bronze plan
- Upgrade to Gold plan effective next month
- Verify pro-rata calculations
- Verify member moved to correct policy

### Test 3: Plan Downgrade
- Select member on Premium plan
- Downgrade to Gold plan effective next month
- Verify refund calculated
- Verify coverage dates correct

### Test 4: Department-Based Assignment
- Upload census with department column
- Map departments to plans
- Verify correct assignment
- Verify unmapped departments use default plan

## Configuration

### Plan Eligibility Rules

Define which plans are available for which employee types:

```php
// config/medical.php
'plan_eligibility' => [
    'Executive' => ['premium', 'gold'],  // Can choose premium or gold
    'Senior' => ['gold', 'silver'],
    'Mid' => ['silver', 'bronze'],
    'Junior' => ['bronze'],
],
```

### Upgrade/Downgrade Rules

```php
'plan_changes' => [
    'allow_upgrades' => true,
    'allow_downgrades' => true,
    'downgrade_waiting_period_days' => 90,  // Can't downgrade within first 90 days
    'max_changes_per_year' => 2,
],
```

## Benefits

1. **Flexible Tier Management**: Support complex organizational structures
2. **Automatic Assignment**: No manual plan selection needed
3. **Career Progression**: Seamless plan upgrades with promotions
4. **Cost Optimization**: Different plans for different employee values
5. **HR Efficiency**: Bulk operations with plan mapping
6. **Financial Accuracy**: Pro-rata calculations ensure fair billing

## Future Enhancements

1. **Plan Comparison Tool**: Side-by-side plan comparison for members
2. **Upgrade Recommendations**: AI-suggested plan upgrades based on usage
3. **Family Plan Handling**: Dependents inherit principal's plan or separate
4. **Waiting Periods**: Enforce waiting periods for certain benefits after upgrade
5. **Plan Credits**: Carry over unused benefits on upgrade

## Summary

The multi-plan group feature enables sophisticated corporate insurance management without any database changes. By leveraging existing fields (`salary_band`, `department`, `job_title`), the system can automatically assign members to appropriate plans and handle plan changes throughout the policy lifecycle.
