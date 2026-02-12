# FlexCare v2 - Codebase Overview

## 🎯 What is FlexCare v2?

**FlexCare v2** is a comprehensive **Medical Insurance Management System** built for insurance companies to manage the complete lifecycle of medical insurance products - from quoting and underwriting to policy management, claims processing, and member portals.

---

## 🏗️ System Architecture

### **Technology Stack**

#### Backend

- **Framework**: Laravel 12 (PHP 8.4)
- **Architecture**: Modular monolith with module-based organization
- **Database**: MySQL/PostgreSQL (Laravel migrations)
- **Authentication**: Laravel Sanctum (API tokens)
- **Authorization**: Spatie Laravel Permission (roles & permissions)
- **Real-time**: Laravel Reverb (WebSockets)
- **PDF Generation**: DomPDF
- **Auditing**: Laravel Auditing

#### Frontend

- **Framework**: Angular 21 (Standalone Components)
- **State Management**: Angular Signals + Custom Signal Stores
- **UI Library**: Angular Material 21
- **Styling**: Tailwind CSS 4
- **Charts**: ApexCharts (ng-apexcharts)
- **Real-time**: Laravel Echo + Pusher.js
- **PDF Export**: jsPDF + jsPDF-AutoTable

#### Architecture Pattern

- **Monorepo Structure**: Multiple Angular applications in one workspace
  - `admin` - Administrative portal for insurance staff
  - `web` - Public-facing website for customers
  - `libs` - Shared libraries (medical features, core utilities)

---

## 📦 Core Modules

### 1. **Medical Module** (Primary Module)

The main insurance module handling medical insurance operations.

**Location**: `backend/Modules/Medical/`

**Key Features**:

- ✅ Product configuration (Schemes, Plans, Benefits, Rate Cards)
- ✅ Quoting engine with real-time premium calculation
- ✅ Application management and underwriting
- ✅ Policy lifecycle management
- ✅ Member management with portal access
- ✅ Claims processing with auto-adjudication
- ✅ Billing and invoicing
- ✅ Provider integration (hospitals/clinics)
- ✅ Fraud detection
- ✅ Pre-authorization workflow
- ✅ Benefit utilization tracking
- ✅ Real-time eligibility checks

**Future Modules** (Configured but not active):

- Motor Insurance
- Electronics Insurance

---

## 🗄️ Database Structure

### **Core Entities**

#### **Product Configuration**

1. **Schemes** (`med_schemes`) - Insurance product lines (e.g., "Corporate Health", "Individual Health")
2. **Plans** (`med_plans`) - Specific coverage plans (e.g., "Gold Plan", "Silver Plan")
3. **Benefits** (`med_benefits`) - Coverage items (e.g., "In-Patient", "Out-Patient", "Dental")
4. **Rate Cards** (`med_rate_cards`) - Pricing tables based on age bands
5. **Addons** (`med_addons`) - Optional coverage extensions (e.g., "Maternity", "Optical")
6. **Discounts** (`med_discount_rules`) - Promotional discounts and promo codes
7. **Loadings** (`med_loading_rules`) - Premium increases for pre-existing conditions

#### **Sales & Underwriting**

1. **Corporate Groups** (`med_corporate_groups`) - Company/organization clients
2. **Quotes** - Premium quotations (stored in applications)
3. **Applications** (`med_applications`) - Insurance applications with members
4. **Application Members** (`med_application_members`) - Individuals on applications

#### **Policy Management**

1. **Policies** (`med_policies`) - Active insurance contracts
2. **Policy Addons** (`med_policy_addons`) - Active addons on policies
3. **Members** (`med_members`) - Covered individuals with member cards
4. **Endorsements** (`med_endorsements`) - Policy changes (add/remove members, plan changes)

#### **Claims & Operations**

1. **Claims** (`med_claims`) - Medical claims submitted
2. **Claim Lines** (`med_claim_lines`) - Individual services/items in claims
3. **Pre-authorizations** (`med_preauthorizations`) - Pre-approved treatments
4. **Benefit Utilization** (`med_benefit_utilization`) - Tracking benefit usage
5. **Fraud Detection** (`med_fraud_detection`) - Fraud alerts and scoring

#### **Billing**

1. **Billing Records** (`med_billing`) - Invoices and payments
2. **Payment Tracking** - Payment status and history

#### **Provider Integration**

1. **Providers** (`med_providers`) - Hospitals, clinics, pharmacies
2. **Provider API Logs** - API request tracking for provider integrations

---

## 🔄 Business Workflow

### **1. Product Setup** (Admin)

```
Scheme → Plans → Benefits → Rate Cards → Addons → Discounts
```

### **2. Sales Process**

```
Corporate Group Creation
    ↓
Quote Generation (Public/Admin)
    ↓
Application Submission
    ↓
Underwriting Review
    ↓
Application Approval
    ↓
Policy Creation (Contract)
    ↓
Member Card Generation
```

### **3. Policy Lifecycle**

```
Active Policy
    ↓
Endorsements (Add/Remove Members, Plan Changes)
    ↓
Renewal (Before Expiry)
    ↓
New Policy (Renewal) or Lapse
```

### **4. Claims Process**

```
Member/Provider Submits Claim
    ↓
Eligibility Check (Real-time)
    ↓
Fraud Detection Scoring
    ↓
Auto-Adjudication (if eligible) or Manual Review
    ↓
Approval/Rejection
    ↓
Payment Processing
    ↓
Benefit Utilization Update
```

---

## 🎨 Frontend Architecture

### **Project Structure**

```
front-end/
├── projects/
│   ├── admin/          # Admin portal (insurance staff)
│   ├── web/            # Public website (customers)
│   └── libs/           # Shared libraries
│       ├── core/       # Core utilities (HTTP, auth, etc.)
│       └── medical/    # Medical module features
│           ├── data/   # Stores, services, models
│           └── feature/# UI components
```

### **State Management Pattern**

Uses **Signal Stores** - a custom pattern built on Angular Signals:

```typescript
// Store manages all state
@Injectable({ providedIn: 'root' })
export class ApplicationStore {
  private readonly state = signal<ApplicationState>({...});

  // Computed signals (read-only)
  readonly items = computed(() => this.state().items);
  readonly loading = computed(() => this.state().loading);

  // Methods update state (no Observables returned)
  loadApplications() {
    this.state.update(s => ({ ...s, loading: true }));
    this.http.get(...).subscribe(res => {
      this.state.update(s => ({ ...s, items: res.data, loading: false }));
    });
  }
}

// Components just read signals
export class ApplicationList {
  readonly store = inject(ApplicationStore);

  ngOnInit() {
    this.store.loadApplications(); // Triggers load
  }
}
```

**Key Stores**:

- `ApplicationStore` - Application management
- `PolicyStore` - Policy management
- `ClaimStore` - Claims processing
- `MemberStore` - Member management
- `PlanStore` - Product configuration

---

## 🔐 Security & Access Control

### **Authentication**

- **Admin Portal**: Laravel Sanctum tokens
- **Member Portal**: Custom member authentication with OTP
- **Provider API**: API key authentication with rate limiting

### **Authorization**

- **Role-Based Access Control (RBAC)**: Spatie Laravel Permission
- **Module Access Control**: Users assigned to specific modules (Medical, Motor, etc.)
- **Permission System**: Granular permissions per action (e.g., `medical.applications.create`)

### **Roles** (Seeded):

- Super Admin
- Admin
- Underwriter
- Claims Officer
- Customer Service
- Broker
- Agent

---

## 🚀 Key Features

### **1. Intelligent Quoting Engine**

- Real-time premium calculation
- Age-based pricing (rate cards)
- Automatic discount application
- Promo code support
- Loading for pre-existing conditions
- Multi-plan support for corporate groups

### **2. Flexible Product Configuration**

- Scheme → Plan → Benefit hierarchy
- Configurable benefit limits (annual, per visit, per day)
- Waiting periods per benefit
- Co-payments and deductibles
- Plan exclusions
- Mandatory/Optional/Included addons

### **3. Corporate Group Management**

- Multi-plan support (different plans for different employee tiers)
- Census upload (bulk member import)
- Salary band-based plan assignment
- Group contacts and portal access

### **4. Advanced Claims Processing**

- Real-time eligibility verification
- Auto-adjudication for low-risk claims
- Fraud detection scoring
- Pre-authorization workflow
- Benefit utilization tracking
- Provider API integration

### **5. Member Portal**

- OTP-based authentication
- View policy details
- Download ID cards
- Submit claims
- Track claim status
- View benefit utilization
- Update profile

### **6. Provider Integration**

- RESTful API for hospitals/clinics
- Real-time eligibility checks (< 500ms SLA)
- Pre-authorization requests
- Claim submission
- Rate limiting (60/min, 1000/hour)
- API request logging

### **7. Real-time Updates**

- WebSocket integration (Laravel Reverb)
- Live claim status updates
- Fraud alerts
- Eligibility check notifications
- Member updates

### **8. Approval Workflows**

- Configurable approval chains
- Multi-step approvals
- Approval groups
- Notification system
- Audit trail

---

## 📊 Reporting & Analytics

### **Available Reports**

- Premium collection reports
- Claims analysis
- Benefit utilization
- Member demographics
- Policy expiry tracking
- Fraud detection summary

### **Export Formats**

- PDF (DomPDF)
- Excel (planned)
- CSV (planned)

---

## 🔧 Configuration

### **Environment Variables** (Key Ones)

```env
# Medical Module
MEDICAL_TAX_RATE=0.05
MEDICAL_QUOTE_VALIDITY_DAYS=30
MEDICAL_AUTO_GENERATE_INVOICE=true
MEDICAL_AUTO_ADJUDICATION_MAX=10000
MEDICAL_FRAUD_BLOCK_THRESHOLD=90

# WebSocket
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=

# Email
MAIL_MAILER=smtp
MAIL_FROM_ADDRESS=noreply@flexcare.com
```

### **Module Configuration**

File: `backend/config/modules.php`

```php
'active' => [
    'Medical'     => true,  // Active
    'Motor'       => false, // Planned
    'Electronics' => false, // Planned
],
```

---

## 📁 Key Files & Directories

### **Backend**

```
backend/
├── app/                          # Core application
│   ├── Http/Controllers/         # Core controllers (users, roles, approvals)
│   ├── Models/                   # Core models
│   ├── Services/                 # Core services
│   └── Providers/                # Service providers
├── Modules/Medical/              # Medical module
│   ├── Http/Controllers/         # Medical controllers
│   ├── Services/                 # Business logic
│   ├── Models/                   # Medical models
│   ├── Database/Migrations/      # Database schema
│   ├── Routes/api.php            # API routes
│   └── Config/medical.php        # Module configuration
├── config/                       # Configuration files
├── database/migrations/          # Core migrations
└── routes/                       # Core routes
```

### **Frontend**

```
front-end/
├── projects/
│   ├── admin/                    # Admin portal
│   │   └── src/app/pages/        # Admin pages
│   ├── web/                      # Public website
│   │   └── src/app/pages/        # Public pages
│   └── libs/
│       ├── core/                 # Core utilities
│       │   └── http/             # HTTP services, auth
│       └── medical/              # Medical module
│           ├── data/             # Stores, services, models
│           │   ├── stores/       # Signal stores
│           │   ├── services/     # API services
│           │   └── models/       # TypeScript interfaces
│           └── feature/          # UI components
│               └── lib/          # Component library
├── angular.json                  # Angular workspace config
└── package.json                  # Dependencies
```

---

## 🔌 API Structure

### **Base URL**: `/api/v1/medical`

### **Public Routes** (No Auth)

- `GET /public/plans` - Browse plans
- `POST /public/quotes` - Generate quote
- `POST /public/applications` - Submit application

### **Admin Routes** (Auth + Permissions)

- `GET /schemes` - List schemes
- `POST /applications` - Create application
- `GET /policies` - List policies
- `POST /claims` - Submit claim

### **Member Portal Routes** (Member Auth)

- `GET /member/portal/dashboard` - Member dashboard
- `GET /member/portal/policy` - View policy
- `POST /member/portal/claims` - Submit claim

### **Provider API Routes** (API Key Auth)

- `POST /provider/eligibility/check` - Check eligibility
- `POST /provider/preauth/request` - Request pre-auth
- `POST /provider/claims/submit` - Submit claim

---

## 🧪 Testing

### **Backend**

- **Framework**: Pest PHP
- **Command**: `php artisan test`

### **Frontend**

- **Framework**: Vitest
- **Command**: `npm test`

---

## 🚀 Getting Started

### **Backend Setup**

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
```

### **Frontend Setup**

```bash
cd front-end
npm install
npm run start:admin  # Admin portal on http://localhost:4200
npm run start:web    # Public website on http://localhost:4201
```

### **WebSocket Server**

```bash
cd backend
php artisan reverb:start
```

---

## 📝 Recent Improvements

### **Architecture Refactoring**

- ✅ Migrated from component-based HTTP calls to Signal Store pattern
- ✅ Centralized state management
- ✅ Removed duplicate state between components and stores
- ✅ Improved code maintainability and testability

### **UI/UX Enhancements**

- ✅ Replaced dialog-based addon selection with inline checkboxes
- ✅ Real-time premium calculation display
- ✅ Automatic handling of mandatory/optional/included addons
- ✅ Improved group detail pages with modern Material Design

### **Feature Additions**

- ✅ Multi-plan support for corporate groups
- ✅ Census upload with bulk member import
- ✅ Member portal with OTP authentication
- ✅ Provider API integration
- ✅ Real-time claims processing
- ✅ Fraud detection system
- ✅ Benefit utilization tracking

---

## 🎯 Business Value

### **For Insurance Companies**

- **Faster Quoting**: Real-time premium calculation
- **Automated Underwriting**: Rule-based auto-approval
- **Efficient Claims**: Auto-adjudication reduces manual work
- **Fraud Prevention**: Built-in fraud detection
- **Better Customer Service**: Member portal reduces support calls

### **For Corporate Clients**

- **Self-Service**: HR can manage members via portal
- **Flexible Plans**: Different plans for different employee tiers
- **Easy Onboarding**: Bulk member upload via census
- **Transparency**: Real-time policy and claim status

### **For Members**

- **Convenience**: Mobile-friendly member portal
- **Instant Access**: Download ID cards anytime
- **Claim Tracking**: Real-time claim status updates
- **Benefit Visibility**: See remaining benefits

### **For Healthcare Providers**

- **Fast Eligibility Checks**: < 500ms response time
- **Pre-authorization**: Reduce claim rejections
- **API Integration**: Seamless system integration
- **Automated Claims**: Faster payment processing

---

## 🔮 Future Roadmap

### **Planned Features**

- [ ] Mobile apps (iOS/Android)
- [ ] AI-powered fraud detection
- [ ] Telemedicine integration
- [ ] Wellness program management
- [ ] Motor insurance module
- [ ] Electronics insurance module
- [ ] Advanced analytics dashboard
- [ ] Multi-currency support
- [ ] Multi-language support

### **Technical Improvements**

- [ ] Microservices migration (optional)
- [ ] GraphQL API (optional)
- [ ] Enhanced caching (Redis)
- [ ] Queue workers for background jobs
- [ ] Automated testing coverage > 80%

---

## 📚 Documentation Files

The codebase includes extensive documentation:

- `ARCHITECTURE_REFACTORING.md` - Signal Store pattern explanation
- `IMPLEMENTATION_COMPLETE.md` - Recent implementation summary
- `ADDON_IMPLEMENTATION_ANALYSIS.md` - Addon feature analysis
- `MULTI_PLAN_GROUPS.md` - Multi-plan support documentation
- `FRONTEND_IMPLEMENTATION_GUIDE.md` - Frontend development guide
- `CENSUS_UPLOAD_SIMPLIFIED.md` - Census upload feature
- `GROUP_DETAIL_MODERNIZATION.md` - UI modernization notes
- `WEBSOCKET_INTEGRATION.md` - Real-time features guide

---

## 🤝 Contributing

### **Code Standards**

- **Backend**: PSR-12 (Laravel Pint)
- **Frontend**: Angular style guide + Prettier
- **Commits**: Conventional commits

### **Development Workflow**

1. Create feature branch
2. Implement changes
3. Write tests
4. Submit pull request
5. Code review
6. Merge to main

---

## 📞 Support & Contact

For questions or issues, refer to:

- Technical documentation in the codebase
- API documentation (Postman collection)
- Database schema diagrams
- Architecture decision records (ADRs)

---

## 📄 License

MIT License (as per Laravel framework)

---

**Last Updated**: February 2026
**Version**: 2.0
**Status**: Active Development
