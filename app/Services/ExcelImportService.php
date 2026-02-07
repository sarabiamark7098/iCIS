<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\DynamicImport;

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
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Batch size for chunked inserts.
     */
    protected int $batchSize = 500;

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

        foreach ($headers as $index => $header) {
            $normalizedHeader = $this->normalize($header);
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
     *
     * @param  string  $filePath
     * @param  int     $sheetIndex
     * @param  int     $importId
     * @param  array   $mapping  headerIndex => ['table' => '…', 'column' => '…']
     *
     * @return array   Statistics
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
                DB::table('profiles')->insert($proRecord);
                $stats['profiles_imported']++;

                // Also insert transaction if mapped
                if (isset($records['transactions']) && $records['transactions']['has_data']) {
                    $txRecord = array_merge(
                        ['import_id' => $importId],
                        $records['transactions']['data'],
                        $timestamps
                    );
                    DB::table('transactions')->insert($txRecord);
                    $stats['transactions_imported']++;
                }

                return;
            }

            if ($benHasName && !$proHasName) {
                // Only one name → save as beneficiary with relationship = "Self"
                $benData = $records['beneficiaries']['data'];
                $benData['relationship'] = 'Self';

                $benRecord = array_merge(
                    ['import_id' => $importId],
                    $benData,
                    $timestamps
                );
                DB::table('beneficiaries')->insert($benRecord);
                $stats['beneficiaries_imported']++;

                // Also insert transaction if mapped
                if (isset($records['transactions']) && $records['transactions']['has_data']) {
                    $txRecord = array_merge(
                        ['import_id' => $importId],
                        $records['transactions']['data'],
                        $timestamps
                    );
                    DB::table('transactions')->insert($txRecord);
                    $stats['transactions_imported']++;
                }

                return;
            }

            if (!$benHasName && $proHasName) {
                // Only profile name → save profile name as beneficiary with "Self"
                $benData = $records['profiles']['data'];
                $benData['relationship'] = 'Self';

                $benRecord = array_merge(
                    ['import_id' => $importId],
                    $benData,
                    $timestamps
                );
                DB::table('beneficiaries')->insert($benRecord);
                $stats['beneficiaries_imported']++;

                if (isset($records['transactions']) && $records['transactions']['has_data']) {
                    $txRecord = array_merge(
                        ['import_id' => $importId],
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

        foreach ($tableMap as $table => $colMap) {
            if (!isset($records[$table]) || !$records[$table]['has_data']) {
                continue;
            }

            $record = array_merge(
                ['import_id' => $importId],
                $records[$table]['data'],
                $timestamps
            );

            DB::table($table)->insert($record);
            $rowInserted = true;

            match ($table) {
                'beneficiaries' => $stats['beneficiaries_imported']++,
                'profiles' => $stats['profiles_imported']++,
                'transactions' => $stats['transactions_imported']++,
                default => null,
            };
        }

        if (!$rowInserted) {
            $stats['skipped_rows']++;
        }
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
