<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\DynamicImport;
use Carbon\Carbon;

class ExcelImportService
{
    /**
     * Tables that are allowed to be imported into.
     */
    protected array $allowedTables = [
        'beneficiaries',
        'profiles',
        'transactions',
    ];

    /**
     * Columns to exclude from mapping (auto-managed by the system).
     */
    protected array $excludedColumns = [
        'id',
        'import_id',
        'beneficiary_id',
        'profile_id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Batch size for chunked inserts.
     */
    protected int $batchSize = 500;

    /**
     * Header aliases: normalized_alias => ['table' => ..., 'column' => ...]
     * Handles common header variants from both file formats.
     */
    protected array $headerAliases = [
        // === PROFILES (main person / client — February CSV format) ===
        'lastname'          => ['table' => 'profiles', 'column' => 'last_name'],
        'firstname'         => ['table' => 'profiles', 'column' => 'first_name'],
        'middlename'        => ['table' => 'profiles', 'column' => 'middle_name'],
        'extraname'         => ['table' => 'profiles', 'column' => 'extension_name'],
        'dob'               => ['table' => 'profiles', 'column' => 'birthday'],
        'clientcategory'    => ['table' => 'profiles', 'column' => 'category'],
        'citymunicipality'  => ['table' => 'profiles', 'column' => 'city'],

        // === BENEFICIARIES (B. prefix columns from February CSV) ===
        'b_last_name'       => ['table' => 'beneficiaries', 'column' => 'last_name'],
        'b_first_name'      => ['table' => 'beneficiaries', 'column' => 'first_name'],
        'b_middle_name'     => ['table' => 'beneficiaries', 'column' => 'middle_name'],
        'b_ext'             => ['table' => 'beneficiaries', 'column' => 'extension_name'],

        // === BENEFICIARIES (Maragusan sheet — columns without B. prefix) ===
        'last_name'         => ['table' => 'beneficiaries', 'column' => 'last_name'],
        'first_name'        => ['table' => 'beneficiaries', 'column' => 'first_name'],
        'middle_name'       => ['table' => 'beneficiaries', 'column' => 'middle_name'],
        'extension_name'    => ['table' => 'beneficiaries', 'column' => 'extension_name'],
        'birthday'          => ['table' => 'beneficiaries', 'column' => 'birthday'],
        'contact_number'    => ['table' => 'beneficiaries', 'column' => 'contact_number'],
        'sub_category'      => ['table' => 'beneficiaries', 'column' => 'sub_category'],
        'subcategory'       => ['table' => 'beneficiaries', 'column' => 'sub_category'],

        // === TRANSACTIONS ===
        'entered_by'            => ['table' => 'transactions', 'column' => 'entered_by'],
        'type_of_assistance1'   => ['table' => 'transactions', 'column' => 'assistance_type'],
        'type_of_assistance'    => ['table' => 'transactions', 'column' => 'assistance_type'],
        'amount1'               => ['table' => 'transactions', 'column' => 'assistance_amount'],
        'amount'                => ['table' => 'transactions', 'column' => 'assistance_amount'],
        'mode_of_release1'      => ['table' => 'transactions', 'column' => 'assistance_mode'],
        'mode_of_release'       => ['table' => 'transactions', 'column' => 'assistance_mode'],
    ];

    // ------------------------------------------------------------------
    //  READING
    // ------------------------------------------------------------------

    /**
     * Read an Excel/CSV file and return every sheet with its headers + sample rows.
     */
    public function readFile(string $filePath): array
    {
        $import = new DynamicImport();
        Excel::import($import, $filePath);

        return [
            'sheets' => $import->getAllSheetsData(),
            'sheet_names' => $import->getSheetNames(),
            'sheet_count' => $import->getSheetCount(),
        ];
    }

    /**
     * Read a specific sheet from the file.
     */
    public function readSheet(string $filePath, int $sheetIndex): array
    {
        $import = new DynamicImport();
        Excel::import($import, $filePath);

        $data = $import->getSheetData($sheetIndex);

        if (!$data) {
            return ['headers' => [], 'rows' => [], 'sample_rows' => []];
        }

        return [
            'headers' => $data['headers'],
            'rows' => $data['rows'],
            'sample_rows' => array_slice($data['rows'], 0, 5),
        ];
    }

    /**
     * Find the index of a sheet named "Clean" (case-insensitive).
     * Returns null if not found.
     */
    public function findCleanSheetIndex(string $filePath): ?int
    {
        $import = new DynamicImport();
        Excel::import($import, $filePath);

        foreach ($import->getSheetNames() as $index => $name) {
            if (Str::lower(trim($name)) === 'clean') {
                return $index;
            }
        }

        return null;
    }

    // ------------------------------------------------------------------
    //  TABLE / COLUMN INFO
    // ------------------------------------------------------------------

    public function getImportableTables(): array
    {
        return $this->allowedTables;
    }

    /**
     * Get the fillable columns for a given table, excluding system columns.
     */
    public function getTableColumns(string $table): array
    {
        $columns = Schema::getColumnListing($table);

        return array_values(array_diff($columns, $this->excludedColumns));
    }

    /**
     * Get columns for every importable table, keyed by table name.
     */
    public function getAllTableColumns(): array
    {
        $result = [];
        foreach ($this->allowedTables as $table) {
            $result[$table] = $this->getTableColumns($table);
        }
        return $result;
    }

    // ------------------------------------------------------------------
    //  SUGGESTION
    // ------------------------------------------------------------------

    /**
     * Suggest a mapping from file headers to table columns by normalizing names.
     * Uses both column-name matching and a custom alias map for known header variants.
     *
     * Returns: [ headerIndex => ['table' => '…', 'column' => '…'] , … ]
     */
    public function suggestMultiTableMapping(array $headers): array
    {
        $allColumns = $this->getAllTableColumns();
        $suggestions = [];

        // Build a lookup: normalized_name => [table, column]
        $lookup = [];
        foreach ($allColumns as $table => $columns) {
            foreach ($columns as $column) {
                $normalized = $this->normalize($column);
                // Prioritize beneficiaries > profiles > transactions
                if (!isset($lookup[$normalized])) {
                    $lookup[$normalized] = ['table' => $table, 'column' => $column];
                }
            }
        }

        // Detect if file has B. prefix columns (February CSV format)
        $hasBPrefixColumns = false;
        foreach ($headers as $header) {
            $norm = $this->normalize($header);
            if (Str::startsWith($norm, 'b_')) {
                $hasBPrefixColumns = true;
                break;
            }
        }

        foreach ($headers as $index => $header) {
            $normalizedHeader = $this->normalize($header);

            // 1. Check alias map first (highest priority)
            if (isset($this->headerAliases[$normalizedHeader])) {
                $alias = $this->headerAliases[$normalizedHeader];
                $suggestions[$index] = $alias;
                continue;
            }

            // 2. If file has B. prefix columns, non-prefixed demographic columns go to profiles
            if ($hasBPrefixColumns) {
                $profileOverrides = [
                    'sex'          => ['table' => 'profiles', 'column' => 'sex'],
                    'civilstatus'  => ['table' => 'profiles', 'column' => 'civil_status'],
                    'civil_status' => ['table' => 'profiles', 'column' => 'civil_status'],
                    'occupation'   => ['table' => 'profiles', 'column' => 'occupation'],
                    'region'       => ['table' => 'profiles', 'column' => 'region'],
                    'province'     => ['table' => 'profiles', 'column' => 'province'],
                    'barangay'     => ['table' => 'profiles', 'column' => 'barangay'],
                    'category'     => ['table' => 'profiles', 'column' => 'category'],
                ];
                if (isset($profileOverrides[$normalizedHeader])) {
                    $suggestions[$index] = $profileOverrides[$normalizedHeader];
                    continue;
                }
            }

            // 3. Fall back to column-name matching
            if (isset($lookup[$normalizedHeader])) {
                $suggestions[$index] = $lookup[$normalizedHeader];
            }
        }

        return $suggestions;
    }

    // ------------------------------------------------------------------
    //  IMPORT — multi-table with batch processing
    // ------------------------------------------------------------------

    /**
     * Import data from one sheet into multiple tables based on user-defined mapping.
     *
     * Name logic:
     *  - Each row is inspected for "first_name" columns mapped to beneficiaries AND profiles.
     *  - If BOTH tables have a first_name mapped AND the row has data in both sets of name
     *    columns → save the beneficiary row AND a separate profile row linked to that
     *    beneficiary.
     *  - If ONLY one set of name columns has data → save it as a beneficiary with
     *    relationship = "Self".
     *  - If beneficiary name columns are empty but profile name columns have data → copy
     *    profile name fields into the beneficiary record.
     */
    public function importWithMultiTableMapping(
        string $filePath,
        int $sheetIndex,
        int $importId,
        array $mapping
    ): array {
        // Validate all referenced tables
        foreach ($mapping as $m) {
            if (!in_array($m['table'], $this->allowedTables)) {
                throw new \InvalidArgumentException("Table [{$m['table']}] is not allowed for import.");
            }
        }

        // Re-read the file
        $import = new DynamicImport();
        Excel::import($import, $filePath);
        $rows = $import->getRows($sheetIndex);

        if (empty($rows)) {
            return $this->emptyStats();
        }

        // Group mappings by table: table => [ headerIndex => column ]
        $tableMap = [];
        foreach ($mapping as $headerIndex => $m) {
            $tableMap[$m['table']][(int) $headerIndex] = $m['column'];
        }

        // Check if we have dual-name logic (both beneficiaries AND profiles have name columns)
        $hasBeneficiaryNames = isset($tableMap['beneficiaries']) && $this->hasNameColumns($tableMap['beneficiaries']);
        $hasProfileNames = isset($tableMap['profiles']) && $this->hasNameColumns($tableMap['profiles']);
        $dualNameMode = $hasBeneficiaryNames && $hasProfileNames;

        $stats = [
            'total_rows' => count($rows),
            'beneficiaries_imported' => 0,
            'profiles_imported' => 0,
            'transactions_imported' => 0,
            'skipped_rows' => 0,
        ];

        // Process in batches
        $chunks = array_chunk($rows, $this->batchSize);

        foreach ($chunks as $chunk) {
            DB::beginTransaction();
            try {
                foreach ($chunk as $row) {
                    $this->processRow(
                        $row,
                        $tableMap,
                        $importId,
                        $dualNameMode,
                        $stats
                    );
                }
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        }

        return $stats;
    }

    /**
     * Process a single row and insert into the correct table(s).
     */
    protected function processRow(
        array $row,
        array $tableMap,
        int $importId,
        bool $dualNameMode,
        array &$stats
    ): void {
        $now = now();
        $timestamps = ['created_at' => $now, 'updated_at' => $now];

        // Build records for each table
        $records = [];
        foreach ($tableMap as $table => $colMap) {
            $record = [];
            $hasData = false;

            foreach ($colMap as $headerIndex => $column) {
                $value = $row[$headerIndex] ?? null;

                // Parse birthday values from various formats
                if ($column === 'birthday' && $value !== null && $value !== '') {
                    $value = $this->parseBirthday($value);
                }

                // Normalize sex values
                if ($column === 'sex' && $value !== null && $value !== '') {
                    $value = $this->normalizeSex($value);
                }

                if ($value !== null && $value !== '') {
                    $hasData = true;
                }
                $record[$column] = $value;
            }

            $records[$table] = ['data' => $record, 'has_data' => $hasData];
        }

        // ---- DUAL-NAME MODE ----
        if ($dualNameMode) {
            $benHasName = $this->recordHasName($records['beneficiaries']['data'] ?? []);
            $proHasName = $this->recordHasName($records['profiles']['data'] ?? []);

            if ($benHasName && $proHasName) {
                // Two names → beneficiary + linked profile
                $benRecord = array_merge(
                    ['import_id' => $importId],
                    $records['beneficiaries']['data'],
                    $timestamps
                );
                $beneficiaryId = DB::table('beneficiaries')->insertGetId($benRecord);
                $stats['beneficiaries_imported']++;

                $proRecord = array_merge(
                    ['import_id' => $importId, 'beneficiary_id' => $beneficiaryId],
                    $records['profiles']['data'],
                    $timestamps
                );
                $profileId = DB::table('profiles')->insertGetId($proRecord);
                $stats['profiles_imported']++;

                // Also insert transaction if mapped, linked to profile and beneficiary
                if (isset($records['transactions']) && $records['transactions']['has_data']) {
                    $txRecord = array_merge(
                        ['import_id' => $importId, 'profile_id' => $profileId, 'beneficiary_id' => $beneficiaryId],
                        $records['transactions']['data'],
                        $timestamps
                    );
                    DB::table('transactions')->insert($txRecord);
                    $stats['transactions_imported']++;
                }

                return;
            }

            if ($benHasName && !$proHasName) {
                // Only beneficiary name → save as beneficiary with relationship = "Self"
                $benData = $records['beneficiaries']['data'];
                $benData['relationship'] = 'Self';

                $benRecord = array_merge(
                    ['import_id' => $importId],
                    $benData,
                    $timestamps
                );
                $beneficiaryId = DB::table('beneficiaries')->insertGetId($benRecord);
                $stats['beneficiaries_imported']++;

                // Still create the profile record if it has other data
                if (isset($records['profiles']) && $records['profiles']['has_data']) {
                    $proRecord = array_merge(
                        ['import_id' => $importId, 'beneficiary_id' => $beneficiaryId],
                        $records['profiles']['data'],
                        $timestamps
                    );
                    $profileId = DB::table('profiles')->insertGetId($proRecord);
                    $stats['profiles_imported']++;
                }

                if (isset($records['transactions']) && $records['transactions']['has_data']) {
                    $txRecord = array_merge(
                        ['import_id' => $importId, 'beneficiary_id' => $beneficiaryId],
                        $records['transactions']['data'],
                        $timestamps
                    );
                    if (isset($profileId)) {
                        $txRecord['profile_id'] = $profileId;
                    }
                    DB::table('transactions')->insert($txRecord);
                    $stats['transactions_imported']++;
                }

                return;
            }

            if (!$benHasName && $proHasName) {
                // Only profile name → copy profile name fields into beneficiary
                $proData = $records['profiles']['data'] ?? [];
                $benData = $records['beneficiaries']['data'] ?? [];

                // Copy name fields from profile to beneficiary where beneficiary is empty
                $nameFields = ['first_name', 'last_name', 'middle_name', 'extension_name'];
                foreach ($nameFields as $field) {
                    if (empty($benData[$field]) && !empty($proData[$field])) {
                        $benData[$field] = $proData[$field];
                    }
                }
                $benData['relationship'] = 'Self';

                $benRecord = array_merge(
                    ['import_id' => $importId],
                    $benData,
                    $timestamps
                );
                $beneficiaryId = DB::table('beneficiaries')->insertGetId($benRecord);
                $stats['beneficiaries_imported']++;

                // Still create the profile record linked to the beneficiary
                $proRecord = array_merge(
                    ['import_id' => $importId, 'beneficiary_id' => $beneficiaryId],
                    $proData,
                    $timestamps
                );
                $profileId = DB::table('profiles')->insertGetId($proRecord);
                $stats['profiles_imported']++;

                if (isset($records['transactions']) && $records['transactions']['has_data']) {
                    $txRecord = array_merge(
                        ['import_id' => $importId, 'profile_id' => $profileId, 'beneficiary_id' => $beneficiaryId],
                        $records['transactions']['data'],
                        $timestamps
                    );
                    DB::table('transactions')->insert($txRecord);
                    $stats['transactions_imported']++;
                }

                return;
            }

            // No name data at all → skip the row
            $stats['skipped_rows']++;
            return;
        }

        // ---- STANDARD MODE (no dual-name logic) ----
        $rowInserted = false;
        $insertedBeneficiaryId = null;
        $insertedProfileId = null;

        // Insert beneficiaries and profiles first to get their IDs
        foreach (['beneficiaries', 'profiles'] as $table) {
            if (!isset($tableMap[$table]) || !isset($records[$table]) || !$records[$table]['has_data']) {
                continue;
            }

            $record = array_merge(
                ['import_id' => $importId],
                $records[$table]['data'],
                $timestamps
            );

            $id = DB::table($table)->insertGetId($record);
            $rowInserted = true;

            if ($table === 'beneficiaries') {
                $insertedBeneficiaryId = $id;
                $stats['beneficiaries_imported']++;
            } elseif ($table === 'profiles') {
                $insertedProfileId = $id;
                $stats['profiles_imported']++;
            }
        }

        // Insert transactions linked to profile and beneficiary
        if (isset($tableMap['transactions']) && isset($records['transactions']) && $records['transactions']['has_data']) {
            $record = array_merge(
                ['import_id' => $importId],
                $records['transactions']['data'],
                $timestamps
            );

            if ($insertedProfileId) {
                $record['profile_id'] = $insertedProfileId;
            }
            if ($insertedBeneficiaryId) {
                $record['beneficiary_id'] = $insertedBeneficiaryId;
            }

            DB::table('transactions')->insert($record);
            $rowInserted = true;
            $stats['transactions_imported']++;
        }

        if (!$rowInserted) {
            $stats['skipped_rows']++;
        }
    }

    /**
     * Parse birthday value from various formats into Y-m-d string.
     */
    protected function parseBirthday($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // If it's a numeric value (Excel serial date number)
        if (is_numeric($value) && (int) $value > 10000) {
            try {
                $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((int) $value);
                return $date->format('Y-m-d');
            } catch (\Exception $e) {
                // Fall through to string parsing
            }
        }

        // Try common date formats
        $formats = ['Y-m-d', 'm/d/Y', 'd/m/Y', 'Y/m/d', 'm-d-Y', 'd-m-Y'];
        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, (string) $value);
                if ($date && $date->year > 1900 && $date->year < 2030) {
                    return $date->format('Y-m-d');
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        // Try Carbon's general parser
        try {
            $date = Carbon::parse((string) $value);
            if ($date->year > 1900 && $date->year < 2030) {
                return $date->format('Y-m-d');
            }
        } catch (\Exception $e) {
            // Unable to parse
        }

        return null;
    }

    /**
     * Normalize sex value to lowercase 'male' or 'female'.
     */
    protected function normalizeSex($value): ?string
    {
        $value = Str::lower(trim((string) $value));

        if (in_array($value, ['male', 'm'])) {
            return 'male';
        }
        if (in_array($value, ['female', 'f'])) {
            return 'female';
        }

        return null;
    }

    /**
     * Check if a column map includes name columns (first_name or last_name).
     */
    protected function hasNameColumns(array $colMap): bool
    {
        $nameColumns = ['first_name', 'last_name'];
        $mappedColumns = array_values($colMap);

        return !empty(array_intersect($nameColumns, $mappedColumns));
    }

    /**
     * Check if a record has actual name data.
     */
    protected function recordHasName(array $record): bool
    {
        $firstName = trim((string) ($record['first_name'] ?? ''));
        $lastName = trim((string) ($record['last_name'] ?? ''));

        return $firstName !== '' || $lastName !== '';
    }

    /**
     * Return empty stats structure.
     */
    protected function emptyStats(): array
    {
        return [
            'total_rows' => 0,
            'beneficiaries_imported' => 0,
            'profiles_imported' => 0,
            'transactions_imported' => 0,
            'skipped_rows' => 0,
        ];
    }

    // ------------------------------------------------------------------
    //  NORMALIZATION
    // ------------------------------------------------------------------

    /**
     * Normalize a string for comparison.
     */
    protected function normalize(string $value): string
    {
        $value = Str::lower(trim($value));
        $value = preg_replace('/[^a-z0-9_\s-]/', '', $value);
        $value = preg_replace('/[\s-]+/', '_', $value);

        return $value;
    }
}
