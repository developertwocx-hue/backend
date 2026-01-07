# Vehicle Compliance Management System

## Overview
Multi-tenant Laravel SaaS application for managing vehicle compliance records with automatic status tracking, scoring, and state/vehicle-type based requirements.

## Database Schema

### Core Tables
- `compliance_types` - Defines types of compliance (registration, insurance, inspection, etc.)
- `vehicle_compliance_requirements` - Links vehicles to required compliance types
- `compliance_records` - Individual compliance submissions with expiry dates
- `compliance_record_documents` - Bridge table linking records to documents
- `compliance_alerts` - Automated alerts for expiring compliance
- `compliance_audit_logs` - Audit trail of all changes

### Key Columns

**vehicles:**
- `state_of_operation` - Australian state code (VIC, NSW, QLD, etc.)
- `compliance_score` - Cached 0-100% score
- `operational_status` - operational/non_operational/maintenance

**compliance_types:**
- `vehicle_type_id` - Nullable, specific vehicle type or all types
- `state_code` - Nullable, specific state or all states
- `tenant_id` - Nullable, tenant custom or global type
- `renewal_frequency_days` - How often renewal needed (365 for annual)
- `is_required` - Whether mandatory
- `requires_document` - Whether document evidence needed
- `alert_thresholds` - JSON array [30, 14, 7, 0] days before expiry

**compliance_records:**
- `vehicle_compliance_requirement_id` - Links to requirement
- `issue_date` - When compliance was issued
- `expiry_date` - When compliance expires
- `is_current` - Only one current record per requirement
- `status` - Computed: 'compliant', 'at_risk', 'expired', 'pending'

## Scope Hierarchy

Compliance types follow a priority hierarchy:
1. **Global** - All vehicles, all states (`tenant_id=null, vehicle_type_id=null, state_code=null`)
2. **State-Specific** - All vehicle types in one state (`tenant_id=null, vehicle_type_id=null, state_code='VIC'`)
3. **Vehicle-Type Specific** - One type, all states (`tenant_id=null, vehicle_type_id=8, state_code=null`)
4. **State + Vehicle Type** - Specific type in specific state (`tenant_id=null, vehicle_type_id=8, state_code='VIC'`)
5. **Tenant Custom** - Company-specific types (`tenant_id=1`)

## Status Calculation

### Record Status (Computed)
- **compliant** - More than 30 days until expiry
- **at_risk** - 0-30 days until expiry
- **expired** - Past expiry date
- **pending** - No expiry date set or no record exists

### Vehicle Compliance Status
Uses worst-case rule across all required compliance:
- Any **expired** → Vehicle is **expired**
- Any **at_risk** → Vehicle is **at_risk**
- Any **pending** → Vehicle is **pending**
- All **compliant** → Vehicle is **compliant**

### Compliance Score
- 0-100% calculated from required compliance only
- **compliant** = 100 points
- **at_risk** = 50 points
- **expired** / **pending** = 0 points
- Score = Sum of points / Total required requirements
- If NO requirements: Score = 0% (not 100%)

## Key Models & Methods

### Vehicle Model
**Location:** `app/Models/Vehicle.php`

**Key Methods:**
- `getComplianceStatusAttribute()` - Computed status (not stored)
- `calculateComplianceScore()` - Returns 0-100 score
- `updateComplianceScore()` - Recalculates and saves to DB
- `generateComplianceRequirements()` - Auto-creates requirements on vehicle creation
- `refreshComplianceRequirements()` - Regenerates after state/type change
- `getComplianceSummary()` - Dashboard summary with counts
- `updateOperationalStatus()` - Sets operational/non_operational based on compliance

**Computed Attribute:**
- `compliance_status` - Returns 'compliant'|'at_risk'|'expired'|'pending'

### ComplianceType Model
**Location:** `app/Models/ComplianceType.php`

**Key Scope:**
- `scopeForVehicle($query, Vehicle $vehicle)` - Filters types applicable to a vehicle
  - Matches vehicle's `vehicle_type_id` and `state_of_operation`
  - Returns global + state + vehicle-type + state+vehicle-type + tenant custom

**Accessor:**
- `getScopeTypeAttribute()` - Returns 'Global'|'State-Specific'|'Vehicle-Type Specific'|'State + Vehicle-Type'|'Tenant Custom'

### ComplianceRecord Model
**Location:** `app/Models/ComplianceRecord.php`

**Key Methods:**
- `getStatusAttribute()` - Computed status based on expiry_date
- Model events automatically:
  - Mark previous records as `is_current = false`
  - Log audit trail
  - Update vehicle compliance score

### VehicleComplianceRequirement Model
**Location:** `app/Models/VehicleComplianceRequirement.php`

**Key Methods:**
- `getCurrentStatus()` - Gets status from current record or 'pending'
- `getDaysUntilExpiry()` - Days until current record expires
- `isOverdue()` - Boolean if expired

## Victorian Compliance Types

**Seeder:** `database/seeders/VictorianComplianceTypesSeeder.php`

### Light Vehicle (< 4.5t GVM)
- Vehicle Registration (VIC) - Annual, required
- CTP Insurance (VIC) - Annual, required
- Certificate of Roadworthiness (VIC) - Event-based, optional

### Van / Mini Van
- Vehicle Registration (VIC) - Annual, required
- CTP Insurance (VIC) - Annual, required
- Certificate of Roadworthiness (VIC) - Event-based, optional
- ADR Compliance Evidence (VIC) - One-time, optional (Van only)

### Truck (Heavy Vehicle >4.5t GVM)
- Heavy Vehicle Registration (VIC) - Annual, required
- CTP Insurance (VIC) - Annual, required
- Compliance Plate / National Standards (VIC) - One-time, required
- VASS Certification (VIC) - One-time, optional
- GVM Compliance Evidence (VIC) - One-time, required

### Crane (Mobile Cranes)
- Heavy Vehicle Registration (VIC) - Annual, **required**
- CTP Insurance (VIC) - Annual, **required**
- Compliance Plate / Heavy Vehicle Documentation (VIC) - One-time, **required**
- Weighbridge Certificate (VIC) - Annual, optional
- NHVR Standards Compliance (VIC) - One-time, optional
- SafeWork Victoria Compliance (VIC) - Annual, **required**

**Total: 4 required, 2 optional for Cranes**

## API Endpoints

**Base:** `/api/vehicles/{vehicleId}/`

### Compliance Records
- `GET /compliance-records` - List all records for vehicle
- `GET /compliance-records/{id}` - Single record with documents
- `POST /compliance-records` - Create with file upload
- `PUT /compliance-records/{id}` - Update record
- `DELETE /compliance-records/{id}` - Delete record

### Status & Helpers
- `GET /compliance/status` - **Dashboard endpoint** - Returns full compliance status
- `GET /compliance/requirements/{requirementId}/history` - Historical records
- `POST /compliance-records/{id}/approve` - Admin approval

### Compliance Types
- `GET /compliance-types` - List types with filters
- `GET /compliance-types?vehicle_type_id=8` - Filter by vehicle type
- `GET /compliance-types?state_code=VIC` - Filter by state
- `GET /compliance-types/categories` - Get all categories
- `GET /vehicles/{vehicleId}/compliance-types` - Get applicable types for vehicle

## Controllers

### ComplianceRecordController
**Location:** `app/Http/Controllers/Api/ComplianceRecordController.php`

**Key Method - getCurrentStatus:**
```php
// GET /vehicles/{vehicleId}/compliance/status
Returns:
{
    "vehicle": {
        "id": 148,
        "vehicle_type_id": 8,
        "vehicle_type_name": "Crane",
        "state_of_operation": "VIC",
        "compliance_status": "pending",
        "compliance_score": "0.00",
        "operational_status": "operational"
    },
    "requirements": {
        "required": [
            {
                "requirement_id": 21,
                "compliance_type_id": 15,
                "compliance_type_name": "Heavy Vehicle Registration (VIC - Crane)",
                "category": "registration",
                "is_required": true,
                "renewal_frequency_days": 365,
                "requires_document": true,
                "status": "pending",
                "current_record": null,
                "days_until_expiry": null,
                "is_overdue": false
            },
            {
                "requirement_id": 22,
                "compliance_type_id": 16,
                "compliance_type_name": "CTP Insurance (VIC - Crane)",
                "category": "insurance",
                "is_required": true,
                "renewal_frequency_days": 365,
                "requires_document": true,
                "status": "pending",
                "current_record": null,
                "days_until_expiry": null,
                "is_overdue": false
            }
        ],
        "optional": [
            {
                "requirement_id": 24,
                "compliance_type_id": 18,
                "compliance_type_name": "Weighbridge Certificate (VIC - Crane)",
                "category": "other",
                "is_required": false,
                "renewal_frequency_days": 365,
                "requires_document": true,
                "status": "pending",
                "current_record": null,
                "days_until_expiry": null,
                "is_overdue": false
            }
        ]
    },
    "summary": {
        "total_requirements": 4,
        "compliant": 0,
        "at_risk": 0,
        "expired": 0,
        "pending": 4,
        "compliance_score": "0.00",
        "overall_status": "pending",
        "can_operate": false
    }
}
```

**Response Structure:**
- `vehicle` - Complete vehicle details including type name and state
- `requirements.required` - Array of required compliance items (affects score)
- `requirements.optional` - Array of optional compliance items
- `summary` - Counts and overall status (calculated from required only)

### ComplianceTypeController
**Location:** `app/Http/Controllers/Api/ComplianceTypeController.php`

**Filters:**
- `vehicle_id` - Uses `ComplianceType::forVehicle()` scope
- `vehicle_type_id` - Filters by vehicle type
- `state_code` - Filters by state
- `category` - Filters by category

## Nova Resources

### ComplianceType
**Location:** `app/Nova/ComplianceType.php`

**Access Control:**
- Superadmins: Can create global/state-specific types, see all types
- Tenants: Can only create custom types (tenant_id auto-filled), see global + own types
- State field: Readonly for tenants, editable for superadmins

**afterCreate Hook:** Auto-fills tenant_id for non-superadmin users

### ComplianceRecord
**Location:** `app/Nova/ComplianceRecord.php`

**File Upload:**
- Field: `compliance_document` (PDF, JPG, PNG, DOC, DOCX - Max 100MB)
- Uses `afterCreate()` hook to handle file upload
- Creates VehicleDocument record
- Links via `compliance_record_documents` bridge table

**Access Control:**
- Tenant field: Auto-filled via `exceptOnForms()`
- Vehicle dropdown: Non-searchable, shows all tenant vehicles as "Type #ID"
- Compliance type dropdown: Shows global + tenant custom types

**afterCreate Hook:**
1. Auto-fills tenant_id from user
2. Auto-fills submitted_by from user
3. Calls handleDocumentUpload() if file provided

### VehicleComplianceRequirement
**Location:** `app/Nova/VehicleComplianceRequirement.php`

**Settings:** `$displayInNavigation = false` (hidden from sidebar)

## Important Business Rules

1. **One Current Record Per Requirement**
   - Model event automatically marks previous records as `is_current = false`
   - Only current record affects compliance status

2. **Vehicle Without State**
   - No state = No state-specific requirements generated
   - Score = 0%, Status = 'pending'

3. **Document Upload**
   - Files stored in `storage/app/public/vehicle-documents/`
   - Creates VehicleDocument record
   - Links via bridge table `compliance_record_documents`
   - Primary document: `is_primary = true` (first uploaded)

4. **Automatic Score Updates**
   - Vehicle model events trigger `updateComplianceScore()` on:
     - Vehicle created
     - Vehicle updated (state or type changed)
     - Compliance record created/updated/deleted

5. **Operational Status**
   - `expired` compliance → `non_operational`
   - `at_risk`, `pending`, `compliant` → `operational`
   - Manual `maintenance` status is never overridden

## Commands

### Recalculate Compliance
**Command:** `php artisan vehicles:recalculate-compliance`
**File:** `app/Console/Commands/RecalculateVehicleCompliance.php`
**Purpose:** Recalculates compliance scores for all vehicles (useful after logic changes)

## Common Issues & Solutions

### Issue: Vehicle shows 100% compliant with no records
**Cause:** Old cached score before logic fix
**Solution:** Run `php artisan vehicles:recalculate-compliance`

### Issue: Crane shows 20 requirements instead of 6
**Cause:** Bug in `ComplianceType::scopeForVehicle()` - state filter not checking vehicle_type_id
**Fix:** Added `whereNull('vehicle_type_id')` to state-specific clause in scope

### Issue: Vehicle has no compliance requirements
**Cause:** Vehicle has no `state_of_operation` set
**Solution:** Set state then call `$vehicle->generateComplianceRequirements()`

### Issue: API sending wrong requirement counts / not sending required items
**Cause:** `/vehicles/{vehicleId}/compliance/status` endpoint was only returning required items, not separating required vs optional
**Fix:** Updated `getCurrentStatus()` to return:
- All requirements separated into `requirements.required` and `requirements.optional` arrays
- Each requirement includes full compliance type details
- Vehicle object includes `vehicle_type_name` and `state_of_operation`
- Summary still calculated from required items only

**Before Fix:**
- `requirements` was flat array of only required items
- Missing optional compliance types
- No vehicle type name in response

**After Fix:**
- `requirements.required` - Array of mandatory compliance items
- `requirements.optional` - Array of optional compliance items
- Each item includes: `compliance_type_id`, `compliance_type_name`, `is_required`, `renewal_frequency_days`, `requires_document`
- Frontend can now properly display all compliance types and distinguish required vs optional

## Testing Examples

### Create Compliance Record via Tinker
```php
$vehicle = Vehicle::find(148);
$requirement = $vehicle->complianceRequirements()->where('is_required', true)->first();

$record = ComplianceRecord::withoutEvents(function () use ($vehicle, $requirement) {
    return ComplianceRecord::create([
        'tenant_id' => $vehicle->tenant_id,
        'vehicle_compliance_requirement_id' => $requirement->id,
        'vehicle_id' => $vehicle->id,
        'compliance_type_id' => $requirement->compliance_type_id,
        'issue_date' => now(),
        'expiry_date' => now()->addYear(),
        'submitted_by' => 1,
        'is_current' => true,
    ]);
});
```

### Check Vehicle Compliance Status
```php
$vehicle = Vehicle::find(148);
echo "Score: {$vehicle->compliance_score}%\n";
echo "Status: {$vehicle->compliance_status}\n";
print_r($vehicle->getComplianceSummary());
```

### Regenerate Requirements After State Change
```php
$vehicle = Vehicle::find(149);
$vehicle->state_of_operation = 'VIC';
$vehicle->save();
$vehicle->refreshComplianceRequirements();
```