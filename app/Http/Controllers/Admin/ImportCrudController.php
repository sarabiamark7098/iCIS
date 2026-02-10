<?php

namespace App\Http\Controllers\Admin;

use App\Jobs\ProcessImportJob;
use App\Models\Import;
use App\Services\ExcelImportService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;

/**
 * Class ImportCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class ImportCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;

    /**
     * Row threshold: files with more rows than this are dispatched to the queue.
     */
    protected int $queueThreshold = 1000;

    public function setup()
    {
        CRUD::setModel(\App\Models\Import::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/import');
        CRUD::setEntityNameStrings('import', 'imports');
    }

    // ------------------------------------------------------------------
    //  LIST
    // ------------------------------------------------------------------

    protected function setupListOperation()
    {
        // Filter by status: active (default), archived, trashed
        $filter = request()->get('show', 'active');

        if ($filter === 'archived') {
            CRUD::addBaseClause('archived');
        } elseif ($filter === 'trashed') {
            CRUD::addBaseClause('onlyTrashed');
        } else {
            CRUD::addBaseClause('active');
        }

        CRUD::column('id');
        CRUD::column('file_name')->label('File Name');
        CRUD::column('target_table')->label('Target Table(s)');
        CRUD::column('status')->label('Status');
        CRUD::column('imported_rows')->label('Imported Rows');

        CRUD::addColumn([
            'name' => 'serving_status',
            'label' => 'Serving Status',
            'type' => 'text',
            'wrapper' => [
                'element' => 'span',
                'class' => function ($crud, $column, $entry) {
                    $colors = [
                        'Daily Served' => 'badge bg-success',
                        'Payout Served' => 'badge bg-info',
                        'Scheduled Payout' => 'badge bg-warning text-dark',
                    ];
                    return $colors[$entry->serving_status] ?? 'badge bg-secondary';
                },
            ],
        ]);

        CRUD::addColumn([
            'name' => 'payout_schedule_date',
            'label' => 'Payout Date',
            'type' => 'date',
        ]);

        CRUD::addColumn([
            'name' => 'remark',
            'label' => 'Remark',
            'type' => 'text',
            'limit' => 50,
        ]);

        CRUD::column('created_at')->label('Imported At');

        CRUD::addButtonFromView('top', 'import_excel', 'import_excel', 'beginning');
        CRUD::addButtonFromView('top', 'import_filter_tabs', 'import_filter_tabs', 'end');

        // Line buttons based on filter
        if ($filter === 'trashed') {
            CRUD::addButtonFromView('line', 'restore_import', 'restore_import', 'beginning');
            CRUD::removeButton('delete');
        } elseif ($filter === 'archived') {
            CRUD::addButtonFromView('line', 'unarchive_import', 'unarchive_import', 'beginning');
        } else {
            CRUD::addButtonFromView('line', 'archive_import', 'archive_import', 'beginning');
        }
    }

    // ------------------------------------------------------------------
    //  SHOW
    // ------------------------------------------------------------------

    protected function setupShowOperation()
    {
        CRUD::column('id');
        CRUD::column('file_name')->label('File Name');
        CRUD::column('target_table')->label('Target Table(s)');
        CRUD::column('status')->label('Import Status');
        CRUD::column('imported_rows')->label('Imported Rows');
        CRUD::column('serving_status')->label('Serving Status');
        CRUD::column('payout_schedule_date')->label('Payout Schedule Date')->type('date');
        CRUD::column('remark')->label('Remark');
        CRUD::column('created_at')->label('Imported At');

        CRUD::addColumn([
            'name' => 'archived_at',
            'label' => 'Archived At',
            'type' => 'datetime',
        ]);
    }

    // ------------------------------------------------------------------
    //  UPDATE (for editing remark, serving_status, payout_schedule_date)
    // ------------------------------------------------------------------

    protected function setupUpdateOperation()
    {
        CRUD::field('remark')->type('textarea')->label('Remark');

        CRUD::addField([
            'name' => 'serving_status',
            'label' => 'Serving Status',
            'type' => 'select_from_array',
            'options' => [
                '' => '-- None --',
                'Daily Served' => 'Daily Served',
                'Payout Served' => 'Payout Served',
                'Scheduled Payout' => 'Scheduled Payout',
            ],
            'allows_null' => true,
        ]);

        CRUD::field('payout_schedule_date')->type('date')->label('Payout Schedule Date')
            ->hint('Only required when Serving Status is "Scheduled Payout".');
    }

    // ------------------------------------------------------------------
    //  STEP 1 — Upload file
    // ------------------------------------------------------------------

    public function showImportForm()
    {
        $this->crud->hasAccessOrFail('list');

        return view('vendor.backpack.crud.import_excel', [
            'crud' => $this->crud,
            'title' => 'Import Excel',
        ]);
    }

    // ------------------------------------------------------------------
    //  STEP 2 — Read file, detect sheets
    // ------------------------------------------------------------------

    public function previewSheets(Request $request, ExcelImportService $importService)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:51200',
        ]);

        $file = $request->file('file');
        $path = $file->store('imports/temp', 'local');
        $fullPath = storage_path('app/private/' . $path);

        try {
            $fileData = $importService->readFile($fullPath);
        } catch (\Exception $e) {
            @unlink($fullPath);
            return back()->with('error', 'Failed to read file: ' . $e->getMessage());
        }

        if ($fileData['sheet_count'] === 0) {
            @unlink($fullPath);
            return back()->with('error', 'No readable sheets found in the file.');
        }

        session([
            'import_temp_path' => $fullPath,
            'import_original_name' => $file->getClientOriginalName(),
        ]);

        // If only 1 sheet, skip sheet selection and go straight to mapping
        if ($fileData['sheet_count'] === 1) {
            $sheetIndex = array_key_first($fileData['sheets']);
            return $this->showMappingPage($importService, $sheetIndex);
        }

        // Auto-detect "Clean" sheet for multi-sheet files
        foreach ($fileData['sheet_names'] as $index => $name) {
            if (strtolower(trim($name)) === 'clean') {
                return $this->showMappingPage($importService, $index);
            }
        }

        // Multiple sheets, no "Clean" sheet found — show sheet selection page
        $sheetsInfo = [];
        foreach ($fileData['sheets'] as $index => $sheet) {
            $sheetsInfo[$index] = [
                'name' => $sheet['name'],
                'header_count' => count($sheet['headers']),
                'row_count' => count($sheet['rows']),
                'sample_headers' => array_slice($sheet['headers'], 0, 6),
            ];
        }

        return view('vendor.backpack.crud.import_sheets', [
            'crud' => $this->crud,
            'title' => 'Select Sheet',
            'sheetsInfo' => $sheetsInfo,
        ]);
    }

    // ------------------------------------------------------------------
    //  STEP 2b — User picked a sheet, show mapping
    // ------------------------------------------------------------------

    public function selectSheet(Request $request, ExcelImportService $importService)
    {
        $request->validate([
            'sheet_index' => 'required|integer|min:0',
        ]);

        return $this->showMappingPage($importService, (int) $request->input('sheet_index'));
    }

    /**
     * Build the mapping page data and return the view.
     */
    protected function showMappingPage(ExcelImportService $importService, int $sheetIndex)
    {
        $filePath = session('import_temp_path');

        if (!$filePath || !file_exists($filePath)) {
            return redirect()->to($this->crud->route)
                ->with('error', 'Import session expired. Please upload the file again.');
        }

        $sheetData = $importService->readSheet($filePath, $sheetIndex);

        if (empty($sheetData['headers'])) {
            return redirect()->route('import.excel-upload')
                ->with('error', 'No headers found in the selected sheet.');
        }

        $tableColumns = $importService->getAllTableColumns();
        $suggestedMappings = $importService->suggestMultiTableMapping($sheetData['headers']);

        session(['import_sheet_index' => $sheetIndex]);

        return view('vendor.backpack.crud.import_preview', [
            'crud' => $this->crud,
            'title' => 'Map Columns',
            'headers' => $sheetData['headers'],
            'sampleRows' => $sheetData['sample_rows'],
            'totalRows' => count($sheetData['rows']),
            'tableColumns' => $tableColumns,
            'suggestedMappings' => $suggestedMappings,
            'sheetIndex' => $sheetIndex,
            'queueThreshold' => $this->queueThreshold,
        ]);
    }

    // ------------------------------------------------------------------
    //  STEP 3 — Process the import
    // ------------------------------------------------------------------

    public function processImport(Request $request, ExcelImportService $importService)
    {
        $request->validate([
            'mapping_table' => 'required|array',
            'mapping_column' => 'required|array',
            'headers' => 'required|array',
            'sheet_index' => 'required|integer|min:0',
        ]);

        $filePath = session('import_temp_path');
        $originalName = session('import_original_name');
        $sheetIndex = (int) $request->input('sheet_index');
        $mappingTables = $request->input('mapping_table');
        $mappingColumns = $request->input('mapping_column');
        $remark = $request->input('remark');
        $servingStatus = $request->input('serving_status');
        $payoutScheduleDate = $request->input('payout_schedule_date');

        if (!$filePath || !file_exists($filePath)) {
            return redirect()->to($this->crud->route)
                ->with('error', 'Import session expired. Please upload the file again.');
        }

        // Build multi-table mapping: headerIndex => ['table' => …, 'column' => …]
        $mapping = [];
        $targetTables = [];
        foreach ($mappingTables as $index => $table) {
            $column = $mappingColumns[$index] ?? '';
            if (!empty($table) && !empty($column)) {
                $mapping[(int) $index] = [
                    'table' => $table,
                    'column' => $column,
                ];
                $targetTables[$table] = true;
            }
        }

        if (empty($mapping)) {
            return back()->with('error', 'No columns were mapped. Please assign at least one header.');
        }

        $targetTablesList = implode(', ', array_keys($targetTables));

        // Count rows to decide sync vs queue
        $sheetData = $importService->readSheet($filePath, $sheetIndex);
        $rowCount = count($sheetData['rows']);

        // Create import record
        $import = Import::create([
            'file_name' => $originalName,
            'target_table' => $targetTablesList,
            'status' => 'pending',
            'remark' => $remark,
            'serving_status' => $servingStatus ?: null,
            'payout_schedule_date' => $servingStatus === 'Scheduled Payout' ? $payoutScheduleDate : null,
        ]);

        // Clean up session
        session()->forget(['import_temp_path', 'import_original_name', 'import_sheet_index']);

        if ($rowCount > $this->queueThreshold) {
            // Large file → dispatch to queue
            $import->update(['status' => 'queued']);
            ProcessImportJob::dispatch($import->id, $filePath, $sheetIndex, $mapping);

            return redirect()->to($this->crud->route)
                ->with('success', "Import queued for background processing ({$rowCount} rows). Status will update when complete.");
        }

        // Small file → process synchronously
        try {
            $import->update(['status' => 'processing']);

            $result = $importService->importWithMultiTableMapping(
                $filePath, $sheetIndex, $import->id, $mapping
            );

            $totalImported = $result['beneficiaries_imported']
                + $result['profiles_imported']
                + $result['transactions_imported'];

            $import->update([
                'status' => 'completed',
                'imported_rows' => $totalImported,
            ]);

            @unlink($filePath);

            $parts = [];
            if ($result['beneficiaries_imported'] > 0) {
                $parts[] = "{$result['beneficiaries_imported']} beneficiaries";
            }
            if ($result['profiles_imported'] > 0) {
                $parts[] = "{$result['profiles_imported']} profiles";
            }
            if ($result['transactions_imported'] > 0) {
                $parts[] = "{$result['transactions_imported']} transactions";
            }
            $message = 'Import completed: ' . implode(', ', $parts) . '.';
            if ($result['skipped_rows'] > 0) {
                $message .= " {$result['skipped_rows']} rows skipped.";
            }

            return redirect()->to($this->crud->route)->with('success', $message);
        } catch (\Exception $e) {
            $import->update(['status' => 'failed']);
            @unlink($filePath);

            return redirect()->to($this->crud->route)
                ->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------
    //  ARCHIVE / UNARCHIVE / RESTORE
    // ------------------------------------------------------------------

    public function archive($id)
    {
        $this->crud->hasAccessOrFail('list');

        $import = Import::findOrFail($id);
        $import->archive();

        return redirect()->back()->with('success', "Import \"{$import->file_name}\" has been archived.");
    }

    public function unarchive($id)
    {
        $this->crud->hasAccessOrFail('list');

        $import = Import::archived()->findOrFail($id);
        $import->unarchive();

        return redirect()->back()->with('success', "Import \"{$import->file_name}\" has been restored from archive.");
    }

    public function restore($id)
    {
        $this->crud->hasAccessOrFail('list');

        $import = Import::onlyTrashed()->findOrFail($id);
        $import->restore();

        return redirect()->back()->with('success', "Import \"{$import->file_name}\" has been restored.");
    }
}
