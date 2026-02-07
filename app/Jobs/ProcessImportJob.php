<?php

namespace App\Jobs;

use App\Models\Import;
use App\Services\ExcelImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected int $importId,
        protected string $filePath,
        protected int $sheetIndex,
        protected array $mapping
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ExcelImportService $importService): void
    {
        $import = Import::findOrFail($this->importId);

        try {
            $import->update(['status' => 'processing']);

            $result = $importService->importWithMultiTableMapping(
                $this->filePath,
                $this->sheetIndex,
                $this->importId,
                $this->mapping
            );

            $totalImported = $result['beneficiaries_imported']
                + $result['profiles_imported']
                + $result['transactions_imported'];

            $import->update([
                'status' => 'completed',
                'imported_rows' => $totalImported,
            ]);
        } catch (\Exception $e) {
            $import->update([
                'status' => 'failed',
            ]);

            throw $e;
        } finally {
            // Clean up the temp file
            if (file_exists($this->filePath)) {
                @unlink($this->filePath);
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $import = Import::find($this->importId);
        if ($import) {
            $import->update(['status' => 'failed']);
        }

        if (file_exists($this->filePath)) {
            @unlink($this->filePath);
        }
    }
}
