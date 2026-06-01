<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

class UserRRPortfolioConverter
{
    /**
     * Convert an uploaded file to safe HTML for display on the portfolio page.
     */
    public function convert(UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $path = $file->getRealPath();

        return match ($ext) {
            'csv' => $this->fromCsv($path),
            'txt' => $this->fromTxt($path),
            'htm', 'html' => $this->fromHtmlFile($path),
            'xlsx', 'xls' => $this->fromSpreadsheet($path),
            default => throw new \InvalidArgumentException('Unsupported format. Use CSV, TXT, HTML, or Excel.'),
        };
    }

    protected function fromCsv(string $path): string
    {
        $rows = [];
        if (($handle = fopen($path, 'r')) === false) {
            throw new \RuntimeException('Could not open CSV file.');
        }
        while (($row = fgetcsv($handle)) !== false) {
            if ($this->rowHasContent($row)) {
                $rows[] = $row;
            }
        }
        fclose($handle);

        return $this->rowsToHtmlTable($rows);
    }

    protected function fromTxt(string $path): string
    {
        $text = file_get_contents($path);
        if ($text === false) {
            throw new \RuntimeException('Could not read text file.');
        }

        return '<div class="rr-portfolio-plain">'.nl2br(e($text)).'</div>';
    }

    protected function fromHtmlFile(string $path): string
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException('Could not read HTML file.');
        }
        $allowed = '<p><br><strong><b><em><i><u><ul><ol><li><table><tr><td><th><thead><tbody><tfoot><caption><h1><h2><h3><h4><h5><h6><div><span><a><colgroup><col><hr><section><article><header><footer>';

        return '<div class="rr-portfolio-html">'.strip_tags($raw, $allowed).'</div>';
    }

    protected function fromSpreadsheet(string $path): string
    {
        $spreadsheet = IOFactory::load($path);
        $rows = $spreadsheet->getActiveSheet()->toArray();

        return $this->rowsToHtmlTable($rows);
    }

    /**
     * @param  array<int, mixed>  $row
     */
    protected function rowHasContent(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    protected function rowsToHtmlTable(array $rows): string
    {
        if (count($rows) === 0) {
            return '<p class="text-muted mb-0">No rows found in file.</p>';
        }

        if (count($rows) === 1) {
            $html = '<div class="table-responsive"><table class="table table-bordered table-striped align-middle rr-portfolio-table"><tbody><tr>';
            foreach ($rows[0] as $cell) {
                $html .= '<td>'.e((string) ($cell ?? '')).'</td>';
            }
            $html .= '</tr></tbody></table></div>';

            return $html;
        }

        $html = '<div class="table-responsive"><table class="table table-bordered table-striped align-middle rr-portfolio-table">';
        $html .= '<thead class="table-light"><tr>';
        foreach ($rows[0] as $cell) {
            $html .= '<th scope="col">'.e((string) ($cell ?? '')).'</th>';
        }
        $html .= '</tr></thead><tbody>';
        for ($i = 1; $i < count($rows); $i++) {
            $html .= '<tr>';
            foreach ($rows[$i] as $cell) {
                $html .= '<td>'.e((string) ($cell ?? '')).'</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table></div>';

        return $html;
    }
}
