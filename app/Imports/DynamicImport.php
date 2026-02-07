<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;

class DynamicImport implements WithMultipleSheets, SkipsUnknownSheets
{
    protected array $sheetData = [];
    protected array $sheetNames = [];

    /**
     * Return a handler for every sheet index.
     * We use a wildcard approach: handle any sheet via onUnknownSheet + explicit index 0.
     */
    public function sheets(): array
    {
        // We return a dynamic handler that catches all sheets via SkipsUnknownSheets
        // and SheetImport instances for sheets 0-20 (practical limit).
        $sheets = [];
        for ($i = 0; $i < 20; $i++) {
            $sheets[$i] = new SheetImport($this, $i);
        }
        return $sheets;
    }

    /**
     * Handle unknown sheets gracefully.
     */
    public function onUnknownSheet($sheetName): void
    {
        // Silently skip sheets beyond our range
    }

    /**
     * Register data from a sheet.
     */
    public function addSheetData(int $index, string $name, array $headers, array $rows): void
    {
        $this->sheetData[$index] = [
            'name' => $name,
            'headers' => $headers,
            'rows' => $rows,
        ];
        $this->sheetNames[$index] = $name;
    }

    /**
     * Get all sheet names.
     */
    public function getSheetNames(): array
    {
        return $this->sheetNames;
    }

    /**
     * Get data from a specific sheet.
     */
    public function getSheetData(int $sheetIndex): ?array
    {
        return $this->sheetData[$sheetIndex] ?? null;
    }

    /**
     * Get all sheets data.
     */
    public function getAllSheetsData(): array
    {
        return $this->sheetData;
    }

    /**
     * Get headers from a specific sheet.
     */
    public function getHeaders(int $sheetIndex = 0): array
    {
        return $this->sheetData[$sheetIndex]['headers'] ?? [];
    }

    /**
     * Get rows from a specific sheet.
     */
    public function getRows(int $sheetIndex = 0): array
    {
        return $this->sheetData[$sheetIndex]['rows'] ?? [];
    }

    /**
     * Get the number of sheets found.
     */
    public function getSheetCount(): int
    {
        return count($this->sheetData);
    }
}
