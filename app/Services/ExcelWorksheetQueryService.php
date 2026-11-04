<?php

namespace App\Services;

use App\Models\ExcelWorksheet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ExcelWorksheetQueryService
{
    /**
     * @param  array{page?: int, per_page?: int, search?: string, sort?: string, direction?: string}  $filters
     */
    public function paginate(ExcelWorksheet $worksheet, array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 25);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        $page = max(1, (int) ($filters['page'] ?? 1));
        $headers = array_values($worksheet->headers ?? []);

        $query = $worksheet->rows()->getQuery();
        $query->where('excel_worksheet_id', $worksheet->id);
        $query->select(['id', 'row_index', 'data']);

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where('search_text', 'like', '%'.$this->escapeLike($search).'%');
        }

        $this->applySort($query, $headers, $filters['sort'] ?? null, $filters['direction'] ?? null);

        return $query->paginate($perPage, ['id', 'row_index', 'data'], 'page', $page);
    }

    /**
     * @param  array<int, string>  $headers
     */
    private function applySort(Builder $query, array $headers, ?string $sort, ?string $direction): void
    {
        $direction = strtolower((string) $direction) === 'desc' ? 'desc' : 'asc';

        if ($sort === null || ! in_array($sort, $headers, true)) {
            $query->orderBy('row_index')->orderBy('id');

            return;
        }

        $path = '$.'.json_encode($sort, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $value = DB::connection($query->getModel()->getConnectionName())->getDriverName() === 'sqlite'
            ? 'json_extract(`data`, ?)'
            : 'JSON_UNQUOTE(JSON_EXTRACT(`data`, ?))';

        $query->orderByRaw(
            "CASE WHEN {$value} IS NULL OR {$value} = '' THEN 1 ELSE 0 END ASC,
             ({$value} + 0) {$direction},
             {$value} {$direction},
             `row_index` ASC",
            [$path, $path, $path, $path]
        );
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }
}
