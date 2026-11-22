<?php

namespace App\Support\Reports;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One report on the Reports page. The same figures feed the screen, the printout and the CSV.
 */
interface SchoolReport
{
    /**
     * The period the figures cover, e.g. "First Term · 2026/2027".
     */
    public function periodLabel(): string;

    /**
     * Everything the report's Blade partial needs.
     *
     * @return array<string, mixed>
     */
    public function figures(): array;

    /**
     * The report's detail rows as a spreadsheet.
     */
    public function csv(): StreamedResponse;
}
