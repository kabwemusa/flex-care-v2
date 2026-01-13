<?php

namespace Modules\Medical\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Modules\Medical\Constants\MedicalConstants;
use Carbon\Carbon;

class CensusImportService
{
    /**
     * Expected CSV/Excel column headers (case-insensitive).
     */
    protected array $expectedHeaders = [
        'first_name',
        'last_name',
        'date_of_birth',
        'gender',
        'email',
        'phone',
        'id_number',
        'employee_number',
        'member_type',
        'relationship', // For dependents
        'principal_employee_number', // Link dependent to principal
    ];

    /**
     * Parse and validate census file.
     *
     * @param UploadedFile $file
     * @return array ['success' => bool, 'data' => array, 'errors' => array, 'summary' => array]
     */
    public function parseCensusFile(UploadedFile $file): array
    {
        try {
            $extension = $file->getClientOriginalExtension();

            if ($extension === 'csv') {
                $data = $this->parseCSV($file);
            } else {
                $data = $this->parseExcel($file);
            }

            if (empty($data)) {
                return [
                    'success' => false,
                    'data' => [],
                    'errors' => [['row' => 0, 'message' => 'No data found in file or invalid file format']],
                    'summary' => $this->generateSummary([]),
                ];
            }

            // Validate parsed data
            $validationResult = $this->validateParsedData($data);

            return [
                'success' => $validationResult['valid_count'] > 0,
                'data' => $data,
                'errors' => $validationResult['errors'],
                'summary' => $this->generateSummary($data, $validationResult),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'data' => [],
                'errors' => [['row' => 0, 'message' => 'Failed to parse file: ' . $e->getMessage()]],
                'summary' => $this->generateSummary([]),
            ];
        }
    }

    /**
     * Parse CSV file.
     */
    protected function parseCSV(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        $rows = [];

        if (($handle = fopen($path, 'r')) !== false) {
            // Read header row
            $headers = fgetcsv($handle);
            if (!$headers) {
                fclose($handle);
                return [];
            }

            // Normalize headers (trim, lowercase)
            $headers = array_map(fn($h) => strtolower(trim($h)), $headers);

            // Read data rows
            $rowNumber = 1;
            while (($data = fgetcsv($handle)) !== false) {
                $rowNumber++;

                // Skip empty rows
                if (empty(array_filter($data))) {
                    continue;
                }

                // Combine headers with data
                $row = array_combine($headers, $data);
                $row['_row_number'] = $rowNumber;
                $rows[] = $row;
            }

            fclose($handle);
        }

        return $rows;
    }

    /**
     * Parse Excel file (.xlsx, .xls).
     */
    protected function parseExcel(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        $spreadsheet = IOFactory::load($path);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = [];

        // Get header row
        $headers = [];
        foreach ($worksheet->getRowIterator(1, 1) as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            foreach ($cellIterator as $cell) {
                $headers[] = strtolower(trim($cell->getValue()));
            }
        }

        // Get data rows
        $rowNumber = 1;
        foreach ($worksheet->getRowIterator(2) as $row) {
            $rowNumber++;
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $rowData = [];
            $colIndex = 0;
            foreach ($cellIterator as $cell) {
                if (isset($headers[$colIndex])) {
                    $value = $cell->getValue();

                    // Handle date values
                    if ($headers[$colIndex] === 'date_of_birth' && is_numeric($value)) {
                        $value = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
                    }

                    $rowData[$headers[$colIndex]] = $value;
                }
                $colIndex++;
            }

            // Skip empty rows
            if (empty(array_filter($rowData))) {
                continue;
            }

            $rowData['_row_number'] = $rowNumber;
            $rows[] = $rowData;
        }

        return $rows;
    }

    /**
     * Validate parsed data against business rules.
     */
    protected function validateParsedData(array $data): array
    {
        $errors = [];
        $validCount = 0;
        $principals = [];
        $dependents = [];

        foreach ($data as $index => $row) {
            $rowNumber = $row['_row_number'] ?? $index + 2;
            $rowErrors = [];

            // Required field validation
            $validator = Validator::make($row, [
                'first_name' => 'required|string|max:100',
                'last_name' => 'required|string|max:100',
                'date_of_birth' => 'required|date|before:today',
                'gender' => 'required|in:M,F,Male,Female',
                'member_type' => 'required|in:' . implode(',', array_keys(MedicalConstants::MEMBER_TYPES)),
                'email' => 'nullable|email',
                'phone' => 'nullable|string',
                'id_number' => 'nullable|string|max:50',
                'employee_number' => 'required_if:member_type,principal|string|max:50',
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $error) {
                    $rowErrors[] = $error;
                }
            }

            // Age validation
            if (isset($row['date_of_birth'])) {
                try {
                    $dob = Carbon::parse($row['date_of_birth']);
                    $age = $dob->age;

                    if ($age < 0 || $age > 120) {
                        $rowErrors[] = "Invalid age calculated from date of birth: {$age}";
                    }
                } catch (\Exception $e) {
                    $rowErrors[] = "Invalid date of birth format";
                }
            }

            // Track principals and dependents for relationship validation
            $memberType = strtolower($row['member_type'] ?? '');
            if ($memberType === 'principal') {
                $employeeNumber = $row['employee_number'] ?? null;
                if ($employeeNumber) {
                    $principals[$employeeNumber] = $rowNumber;
                }
            } else {
                $dependents[] = [
                    'row' => $rowNumber,
                    'principal_employee_number' => $row['principal_employee_number'] ?? null,
                ];
            }

            if (empty($rowErrors)) {
                $validCount++;
            } else {
                $errors[] = [
                    'row' => $rowNumber,
                    'errors' => $rowErrors,
                ];
            }
        }

        // Validate dependent relationships
        foreach ($dependents as $dependent) {
            $principalEmpNum = $dependent['principal_employee_number'];
            if ($principalEmpNum && !isset($principals[$principalEmpNum])) {
                $errors[] = [
                    'row' => $dependent['row'],
                    'errors' => ["Principal with employee number '{$principalEmpNum}' not found in census"],
                ];
            }
        }

        return [
            'errors' => $errors,
            'valid_count' => $validCount,
            'invalid_count' => count($errors),
        ];
    }

    /**
     * Generate summary statistics.
     */
    protected function generateSummary(array $data, ?array $validationResult = null): array
    {
        $totalRows = count($data);
        $validCount = $validationResult['valid_count'] ?? 0;
        $invalidCount = $validationResult['invalid_count'] ?? 0;

        // Count by member type
        $memberTypeCounts = [];
        foreach ($data as $row) {
            $type = $row['member_type'] ?? 'unknown';
            $memberTypeCounts[$type] = ($memberTypeCounts[$type] ?? 0) + 1;
        }

        return [
            'total_rows' => $totalRows,
            'valid_rows' => $validCount,
            'invalid_rows' => $invalidCount,
            'member_type_breakdown' => $memberTypeCounts,
            'has_errors' => $invalidCount > 0,
        ];
    }

    /**
     * Transform parsed data into application member format.
     */
    public function transformToMemberData(array $censusData, string $groupId): array
    {
        $members = [];
        $principalMap = []; // Map employee_number to member index

        // First pass: Create all principals
        foreach ($censusData as $row) {
            if (strtolower($row['member_type']) === 'principal') {
                $memberData = $this->mapRowToMember($row, $groupId);
                $members[] = $memberData;

                if (isset($row['employee_number'])) {
                    $principalMap[$row['employee_number']] = count($members) - 1;
                }
            }
        }

        // Second pass: Create dependents and link to principals
        foreach ($censusData as $row) {
            if (strtolower($row['member_type']) !== 'principal') {
                $memberData = $this->mapRowToMember($row, $groupId);

                // Link to principal if specified
                if (isset($row['principal_employee_number']) && isset($principalMap[$row['principal_employee_number']])) {
                    $memberData['principal_index'] = $principalMap[$row['principal_employee_number']];
                }

                $members[] = $memberData;
            }
        }

        return $members;
    }

    /**
     * Map census row to member data structure.
     */
    protected function mapRowToMember(array $row, string $groupId): array
    {
        // Normalize gender
        $gender = strtoupper(substr($row['gender'] ?? 'M', 0, 1));

        return [
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'date_of_birth' => $row['date_of_birth'],
            'gender' => $gender,
            'email' => $row['email'] ?? null,
            'phone' => $row['phone'] ?? null,
            'id_number' => $row['id_number'] ?? null,
            'member_type' => $row['member_type'],
            'relationship' => $row['relationship'] ?? null,
            'employee_number' => $row['employee_number'] ?? null,
            'salary_band' => $row['salary_band'] ?? null,
            'department' => $row['department'] ?? null,
            'job_title' => $row['job_title'] ?? null,
            'group_id' => $groupId,
        ];
    }
}
