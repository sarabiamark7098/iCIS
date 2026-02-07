<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeSheet;

class SheetImport implements ToArray, WithEvents
{
    protected DynamicImport $parent;
    protected int $sheetIndex;
    protected string $sheetTitle = '';

    public function __construct(DynamicImport $parent, int $sheetIndex)
    {
        $this->parent = $parent;
        $this->sheetIndex = $sheetIndex;
    }

    /**
     * Register events to capture the sheet title.
     */
    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $this->sheetTitle = $event->getSheet()->getTitle();
            },
        ];
    }

    /**
     * Process the sheet data.
     */
    public function array(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        // First row is the header row
        $headers = array_map(fn($h) => trim((string) $h), array_shift($rows));

        // Filter out completely empty headers
        $validHeaders = array_filter($headers, fn($h) => $h !== '');

        if (empty($validHeaders)) {
            return;
        }

        $this->parent->addSheetData(
            $this->sheetIndex,
            $this->sheetTitle ?: 'Sheet ' . ($this->sheetIndex + 1),
            $headers,
            $rows
        );
    }
}
