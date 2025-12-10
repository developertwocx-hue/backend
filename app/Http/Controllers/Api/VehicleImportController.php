<?php

namespace App\Http\Controllers\Api;

use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\VehicleTypeField;
use App\Models\VehicleFieldValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class VehicleImportController extends ApiController
{
    /**
     * Generate import template for a vehicle type
     * GET /api/vehicle-types/{id}/import-template
     */
    public function generateTemplate($vehicleTypeId)
    {
        $user = request()->user();

        // Get vehicle type
        $vehicleType = VehicleType::find($vehicleTypeId);
        if (!$vehicleType) {
            return $this->errorResponse('Vehicle type not found', 404);
        }

        // Get all fields for this vehicle type (default + tenant custom)
        $fields = VehicleTypeField::where('vehicle_type_id', $vehicleTypeId)
            ->where('is_active', true)
            ->where(function($query) use ($user) {
                $query->whereNull('tenant_id')
                      ->orWhere('tenant_id', $user->tenant_id);
            })
            ->orderBy('id')
            ->get();

        // Create spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Vehicle Import');

        $col = 1;
        foreach ($fields as $field) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);

            // Row 1: Field Labels (with * for required)
            $label = $field->name ?? ucfirst(str_replace('_', ' ', $field->key));
            if ($field->is_required) {
                $label .= ' *';
            }
            $sheet->setCellValue($columnLetter . '1', $label);

            // Row 2: Example Values
            $exampleValue = $this->generateExampleValue($field);
            $sheet->setCellValue($columnLetter . '2', $exampleValue);

            // Row 3: Descriptions
            $description = $field->description ?? $this->getFieldDescription($field);
            $sheet->setCellValue($columnLetter . '3', $description);

            // Row 4: Demo Entry
            $demoValue = $this->generateDemoValue($field, $col);
            $sheet->setCellValue($columnLetter . '4', $demoValue);

            $col++;
        }

        // Style header row
        $sheet->getStyle('1:1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);

        // Style example row
        $sheet->getStyle('2:2')->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '64748B']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']]
        ]);

        // Style description row
        $sheet->getStyle('3:3')->applyFromArray([
            'font' => ['size' => 9, 'color' => ['rgb' => '94A3B8']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']]
        ]);

        // Style demo row
        $sheet->getStyle('4:4')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF3C7']]
        ]);

        // Auto-size columns
        foreach (range(1, $col - 1) as $columnIndex) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex);
            $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
        }

        // Generate and download
        $writer = new Xlsx($spreadsheet);
        $fileName = str_replace(' ', '_', $vehicleType->name) . '_import_template.xlsx';

        $temp = tempnam(sys_get_temp_dir(), 'xlsx');
        $writer->save($temp);

        return response()->download($temp, $fileName)->deleteFileAfterSend(true);
    }

    /**
     * Preview import data with validation
     * POST /api/vehicles/import/preview
     */
    public function preview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls,csv',
            'vehicle_type_id' => 'required|exists:vehicle_types,id',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $user = $request->user();
        $vehicleTypeId = $request->vehicle_type_id;
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 20);

        // Get fields for this vehicle type
        $fields = VehicleTypeField::where('vehicle_type_id', $vehicleTypeId)
            ->where('is_active', true)
            ->where(function($query) use ($user) {
                $query->whereNull('tenant_id')
                      ->orWhere('tenant_id', $user->tenant_id);
            })
            ->get()
            ->keyBy('key');

        // Read file
        $file = $request->file('file');
        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray();

        // Get headers from row 1
        $headers = $data[0];
        $rows = [];
        $validRows = 0;
        $invalidRows = 0;

        // Start from row 5 (skip header, example, description, demo)
        for ($i = 4; $i < count($data); $i++) {
            $rowData = $data[$i];

            // Skip empty rows
            if (empty(array_filter($rowData))) {
                continue;
            }

            $rowNumber = $i + 1;
            $vehicleData = [];
            $errors = [];
            $providedFieldKeys = [];

            // Map row data to fields
            foreach ($headers as $colIndex => $header) {
                $cleanHeader = trim(str_replace('*', '', $header));
                $value = $rowData[$colIndex] ?? null;

                // Find matching field by name
                $field = $fields->first(function($f) use ($cleanHeader) {
                    return strtolower($f->name ?? $f->key) === strtolower($cleanHeader);
                });

                if ($field) {
                    $vehicleData[$field->key] = $value;
                    $providedFieldKeys[] = $field->key;

                    // Validate field
                    $fieldErrors = $this->validateField($field, $value, $user->tenant_id);
                    $errors = array_merge($errors, $fieldErrors);
                }
            }

            // Check if all required fields are present
            $requiredFields = $fields->filter(function($f) {
                return $f->is_required ?? false;
            });

            foreach ($requiredFields as $requiredField) {
                if (!in_array($requiredField->key, $providedFieldKeys)) {
                    $errors[] = "{$requiredField->name} is required but missing from uploaded file";
                }
            }

            $isValid = empty($errors);
            if ($isValid) {
                $validRows++;
            } else {
                $invalidRows++;
            }

            $rows[] = [
                'rowNumber' => $rowNumber,
                'data' => $vehicleData,
                'errors' => $errors,
                'isValid' => $isValid
            ];
        }

        // Calculate pagination
        $totalRows = count($rows);
        $totalPages = ceil($totalRows / $perPage);
        $offset = ($page - 1) * $perPage;
        $paginatedRows = array_slice($rows, $offset, $perPage);

        return $this->successResponse([
            'totalRows' => $totalRows,
            'validRows' => $validRows,
            'invalidRows' => $invalidRows,
            'rows' => $paginatedRows,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_pages' => $totalPages,
                'has_more' => $page < $totalPages
            ]
        ]);
    }

    /**
     * Execute bulk import
     * POST /api/vehicles/import
     */
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls,csv',
            'vehicle_type_id' => 'required|exists:vehicle_types,id',
            'edited_rows' => 'nullable|json'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $user = $request->user();
        $vehicleTypeId = $request->vehicle_type_id;

        // Parse edited rows if provided
        $editedRows = $request->edited_rows ? json_decode($request->edited_rows, true) : [];
        $editedRowsMap = [];
        foreach ($editedRows as $editedRow) {
            $editedRowsMap[$editedRow['rowNumber']] = $editedRow['data'];
        }

        // Get fields for this vehicle type
        $fields = VehicleTypeField::where('vehicle_type_id', $vehicleTypeId)
            ->where('is_active', true)
            ->where(function($query) use ($user) {
                $query->whereNull('tenant_id')
                      ->orWhere('tenant_id', $user->tenant_id);
            })
            ->get()
            ->keyBy('key');

        // Read file
        $file = $request->file('file');
        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray();

        // Get headers from row 1
        $headers = $data[0];
        $imported = 0;

        DB::beginTransaction();

        try {
            // Start from row 5 (skip header, example, description, demo)
            for ($i = 4; $i < count($data); $i++) {
                $rowData = $data[$i];

                // Skip empty rows
                if (empty(array_filter($rowData))) {
                    continue;
                }

                $rowNumber = $i + 1;
                $vehicleData = [
                    'status' => 'active',
                    'fieldValues' => []
                ];
                $rowErrors = [];

                // Check if this row has edited data
                $hasEditedData = isset($editedRowsMap[$rowNumber]);
                $editedData = $hasEditedData ? $editedRowsMap[$rowNumber] : [];

                // Validate ALL fields first and collect errors
                foreach ($headers as $colIndex => $header) {
                    $cleanHeader = trim(str_replace('*', '', $header));

                    // Find matching field by name
                    $field = $fields->first(function($f) use ($cleanHeader) {
                        return strtolower($f->name ?? $f->key) === strtolower($cleanHeader);
                    });

                    if ($field) {
                        // Use edited value if available, otherwise use file value
                        $value = $hasEditedData && isset($editedData[$field->key])
                            ? $editedData[$field->key]
                            : ($rowData[$colIndex] ?? null);

                        // Validate field
                        $fieldErrors = $this->validateField($field, $value, $user->tenant_id);

                        if (!empty($fieldErrors)) {
                            $rowErrors = array_merge($rowErrors, $fieldErrors);
                        } else {
                            $vehicleData['fieldValues'][] = [
                                'field_id' => $field->id,
                                'value' => $value
                            ];
                        }
                    }
                }

                // Check if all required fields are present
                $providedFieldIds = array_column($vehicleData['fieldValues'], 'field_id');
                $requiredFields = $fields->filter(function($f) {
                    return $f->is_required ?? false;
                });

                foreach ($requiredFields as $requiredField) {
                    if (!in_array($requiredField->id, $providedFieldIds)) {
                        $rowErrors[] = "{$requiredField->name} is required but missing";
                    }
                }

                // Only create vehicle if there are NO validation errors
                if (!empty($rowErrors)) {
                    throw new \Exception("Row {$rowNumber} has validation errors: " . implode(', ', $rowErrors));
                }

                // Create vehicle
                $vehicle = Vehicle::create([
                    'tenant_id' => $user->tenant_id,
                    'vehicle_type_id' => $vehicleTypeId,
                    'status' => $vehicleData['status']
                ]);

                // Create field values
                foreach ($vehicleData['fieldValues'] as $fieldValue) {
                    VehicleFieldValue::create([
                        'vehicle_id' => $vehicle->id,
                        'vehicle_type_field_id' => $fieldValue['field_id'],
                        'value' => $fieldValue['value']
                    ]);
                }

                $imported++;
            }

            DB::commit();

            return $this->successResponse([
                'imported' => $imported
            ], "Successfully imported {$imported} vehicles");

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Import failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Generate example value for a field
     */
    private function generateExampleValue($field)
    {
        switch ($field->type) {
            case 'number':
                return '12345';
            case 'date':
                return '2024-01-15';
            case 'email':
                return 'example@email.com';
            case 'phone':
                return '+1234567890';
            case 'url':
                return 'https://example.com';
            default:
                if ($field->options) {
                    $options = json_decode($field->options, true);
                    return is_array($options) && !empty($options) ? $options[0] : 'Example Value';
                }
                return 'Example Value';
        }
    }

    /**
     * Generate demo value for a field
     */
    private function generateDemoValue($field, $index)
    {
        $demoValues = [
            'name' => 'Ford F-150 XLT',
            'license_plate' => 'ABC-1234',
            'vin' => '1FTFW1E84MFC12345',
            'model' => 'F-150',
            'make' => 'Ford',
            'year' => '2023',
            'color' => 'Blue',
            'mileage' => '15000',
            'purchase_date' => '2023-01-15',
            'purchase_price' => '45000'
        ];

        $key = strtolower($field->key);
        if (isset($demoValues[$key])) {
            return $demoValues[$key];
        }

        return $this->generateExampleValue($field);
    }

    /**
     * Get field description
     */
    private function getFieldDescription($field)
    {
        $desc = ucfirst($field->type);
        if ($field->is_required) {
            $desc .= ' (Required)';
        }
        if ($field->options) {
            $options = json_decode($field->options, true);
            if (is_array($options)) {
                $desc .= ' - Options: ' . implode(', ', $options);
            }
        }
        return $desc;
    }

    /**
     * Validate a field value
     */
    private function validateField($field, $value, $tenantId)
    {
        $errors = [];

        // Check required
        if ($field->is_required && empty($value)) {
            $errors[] = "{$field->name} is required";
            return $errors;
        }

        // Skip validation if empty and not required
        if (empty($value) && !$field->is_required) {
            return $errors;
        }

        // Type validation
        switch ($field->type) {
            case 'number':
                if (!is_numeric($value)) {
                    $errors[] = "{$field->name} must be a number";
                }
                break;
            case 'date':
                if (!strtotime($value)) {
                    $errors[] = "{$field->name} must be a valid date";
                }
                break;
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "{$field->name} must be a valid email";
                }
                break;
            case 'url':
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    $errors[] = "{$field->name} must be a valid URL";
                }
                break;
        }

        // Options validation
        if ($field->options) {
            $options = json_decode($field->options, true);
            if (is_array($options) && !in_array($value, $options)) {
                $errors[] = "{$field->name} must be one of: " . implode(', ', $options);
            }
        }

        // Duplicate check for unique fields
        if ($field->key === 'license_plate' || $field->key === 'vin') {
            $exists = VehicleFieldValue::whereHas('vehicle', function($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId);
            })
            ->where('vehicle_type_field_id', $field->id)
            ->where('value', $value)
            ->exists();

            if ($exists) {
                $errors[] = "{$field->name} '{$value}' already exists";
            }
        }

        return $errors;
    }
}
