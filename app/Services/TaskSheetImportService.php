<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use App\Policies\TaskPolicy;
use App\Support\SuperAdminAccess;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class TaskSheetImportService
{
    public const TEMPLATE_HEADERS = [
        'Task',
        'Assignee',
        'Assignee 2',
        'Assignee 3',
        'Assignor',
        'Group',
        'Priority',
        'Status',
        'Description',
        'ETC Minutes',
        'Start Date',
        'L1',
        'L2',
        'Training',
        'Video',
        'Form',
        'Form Report',
        'Checklist',
        'PL',
        'Process',
        'Corrective Action',
    ];

    private const HEADER_ALIASES = [
        'task' => 'title',
        'title' => 'title',
        'task title' => 'title',
        'assignee' => 'assignee',
        'assign to' => 'assignee',
        'assign_to' => 'assignee',
        'assigned to' => 'assignee',
        'assignees' => 'assignee',
        'assignee 2' => 'assignee_2',
        'assignee2' => 'assignee_2',
        'assignee 3' => 'assignee_3',
        'assignee3' => 'assignee_3',
        'assignor' => 'assignor',
        'assigned by' => 'assignor',
        'group' => 'group',
        'priority' => 'priority',
        'status' => 'status',
        'description' => 'description',
        'etc' => 'etc_minutes',
        'etc minutes' => 'etc_minutes',
        'eta' => 'etc_minutes',
        'eta time' => 'etc_minutes',
        'eta_time' => 'etc_minutes',
        'start date' => 'tid',
        'tid' => 'tid',
        'start_date' => 'tid',
        'l1' => 'l1',
        'link1' => 'l1',
        'l2' => 'l2',
        'link2' => 'l2',
        'training' => 'training_link',
        'training link' => 'training_link',
        'sop' => 'training_link',
        'video' => 'video_link',
        'video link' => 'video_link',
        'form' => 'form_link',
        'form link' => 'form_link',
        'form report' => 'form_report_link',
        'form report link' => 'form_report_link',
        'report' => 'form_report_link',
        'checklist' => 'checklist_link',
        'checklist link' => 'checklist_link',
        'cl' => 'checklist_link',
        'pl' => 'pl',
        'process' => 'process',
        'links' => 'links',
        'corrective action' => 'is_corrective_action',
        'ca' => 'is_corrective_action',
        'is corrective action' => 'is_corrective_action',
    ];

    private const POSITIONAL_KEYS = [
        'group',
        'title',
        'assignor',
        'assignee',
        'status',
        'priority',
        'image',
        'links',
    ];

    public function __construct(
        protected TaskWhatsAppNotificationService $taskWhatsApp
    ) {}

    private ?int $nextTaskId = null;

    /**
     * @return list<list<string>>
     */
    public static function templateSampleRows(): array
    {
        return [array_fill(0, count(self::TEMPLATE_HEADERS), '')];
    }

    /**
     * @return array{imported:int,skipped:int,errors:list<string>,warnings:list<string>,tasks:list<array<string,mixed>>,message:string}
     */
    public function import(UploadedFile $file, User $actor): array
    {
        $rows = $this->readRows($file);
        if ($rows === []) {
            return [
                'imported' => 0,
                'skipped' => 0,
                'errors' => ['The sheet is empty.'],
                'warnings' => [],
                'tasks' => [],
                'message' => 'No rows found in the sheet.',
            ];
        }

        $users = User::query()->get(['id', 'name', 'email']);
        $isAdmin = SuperAdminAccess::isTaskAdmin($actor);
        $mapped = $this->mapSpreadsheetRows($rows);
        $imported = 0;
        $skipped = 0;
        $errors = [];
        $warnings = [];
        $created = [];

        foreach ($mapped as $index => $row) {
            $sheetRow = $index + 2;
            if ($this->isExampleTemplateRow($row)) {
                continue;
            }
            try {
                $payload = $this->mapRowToTaskData($row, $actor, $isAdmin, $users);
                $task = $this->createAssignedTask($payload);
                $this->notifyAssignees($task, $warnings);
                $created[] = [
                    'id' => $task->id,
                    'title' => $task->title,
                    'assignor' => $task->assignor,
                    'assign_to' => $task->assign_to,
                    'group' => $task->group,
                    'priority' => $task->priority,
                    'status' => $task->status,
                ];
                $imported++;
            } catch (\InvalidArgumentException $e) {
                $skipped++;
                $errors[] = 'Row '.$sheetRow.': '.$e->getMessage();
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = 'Row '.$sheetRow.': '.$this->publicRowError($e);
                Log::warning('Task sheet import row failed', [
                    'row' => $sheetRow,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $message = $imported.' task(s) imported successfully!';
        if ($imported === 0 && $skipped === 0) {
            $message = 'No task rows found. Type a task name, pick Assignee from the dropdown, then upload again.';
        } elseif ($skipped > 0) {
            $message .= ' '.$skipped.' row(s) skipped.';
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'warnings' => $warnings,
            'tasks' => $created,
            'message' => $message,
        ];
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return list<array<string, string>>
     */
    public function mapSpreadsheetRows(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $header = array_map(fn ($cell) => $this->normalizeHeader((string) $cell), $rows[0] ?? []);
        $useHeaders = $this->headerMap($header) !== [];
        $dataRows = $useHeaders ? array_slice($rows, 1) : $rows;
        $mapped = [];

        foreach ($dataRows as $row) {
            $values = array_map(fn ($cell) => trim((string) ($cell ?? '')), is_array($row) ? $row : []);
            if ($this->rowIsEmpty($values)) {
                continue;
            }
            $mapped[] = $useHeaders
                ? $this->rowFromHeaders($header, $values)
                : $this->rowFromPosition($values);
        }

        return $mapped;
    }

    /**
     * @param  array<string, string>  $row
     * @param  Collection<int, User>|iterable<int, User>  $users
     * @return array<string, mixed>
     */
    public function mapRowToTaskData(array $row, User $actor, bool $isAdmin, $users): array
    {
        $title = trim((string) ($row['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('Task title is required.');
        }
        if (mb_strlen($title) > 1000) {
            throw new \InvalidArgumentException('Task title is too long.');
        }

        $assigneeRaw = $this->combinedAssigneeRaw($row);
        if ($assigneeRaw === '') {
            throw new \InvalidArgumentException('Assignee is required.');
        }

        $assigneeEmails = [];
        $unresolved = [];
        foreach ($this->splitPeople($assigneeRaw) as $person) {
            $user = $this->findUser($person, $users);
            if ($user && $user->email) {
                $assigneeEmails[] = $user->email;
            } else {
                $unresolved[] = $person;
            }
        }
        $assigneeEmails = array_values(array_unique($assigneeEmails));
        if ($assigneeEmails === []) {
            throw new \InvalidArgumentException('Assignee not found: '.$assigneeRaw);
        }
        if ($unresolved !== []) {
            throw new \InvalidArgumentException('Assignee not found: '.implode(', ', $unresolved));
        }

        $assignorEmail = $actor->email;
        $assignorRaw = trim((string) ($row['assignor'] ?? ''));
        if ($isAdmin && $assignorRaw !== '') {
            $assignor = $this->findUser($assignorRaw, $users);
            if (! $assignor || ! $assignor->email) {
                throw new \InvalidArgumentException('Assignor not found: '.$assignorRaw);
            }
            $assignorEmail = $assignor->email;
        }

        $startDate = $this->parseStartDate($row['tid'] ?? null);
        $completionDate = Carbon::parse($startDate)->addDays(5);
        $etcMinutes = $this->parseEtcMinutes($row['etc_minutes'] ?? null);
        $links = $this->parseLinks($row);

        return [
            'title' => $title,
            'description' => $this->nullableString($row['description'] ?? null),
            'group' => $this->nullableString($row['group'] ?? null),
            'priority' => $this->mapPriority($row['priority'] ?? null),
            'assignor' => $assignorEmail,
            'assign_to' => implode(', ', $assigneeEmails),
            'split_tasks' => 0,
            'parent_task_id' => null,
            'subtask_order' => 0,
            'status' => $this->mapStatus($row['status'] ?? null),
            'eta_time' => $etcMinutes,
            'start_date' => $startDate,
            'completion_date' => $completionDate,
            'due_date' => $completionDate,
            'completion_day' => 0,
            'etc_done' => 0,
            'is_missed' => 0,
            'is_missed_track' => 0,
            'workspace' => 0,
            'order' => 0,
            'task_id' => '',
            'link1' => $links['l1'],
            'link2' => $links['l2'],
            'link3' => $links['training_link'],
            'link4' => $links['video_link'],
            'link5' => $links['form_link'],
            'link6' => $links['form_report_link'],
            'link7' => $links['checklist_link'],
            'link8' => $links['pl'],
            'link9' => $links['process'],
            'image' => null,
            'is_data_from' => 0,
            'is_automate_task' => 0,
            'task_type' => 'manual',
            'rework_reason' => '',
            'delete_rating' => 0,
            'delete_feedback' => '',
            'is_corrective_action' => $this->parseYesNo($row['is_corrective_action'] ?? null),
        ];
    }

    public function findUser(string $value, $users): ?User
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $pool = $users instanceof Collection ? $users : collect($users);
        foreach ($pool as $candidate) {
            if ($candidate instanceof User && TaskPolicy::userIsAssignor($candidate, $value)) {
                return $candidate;
            }
        }

        $lower = strtolower($value);
        $partial = $pool->filter(function ($candidate) use ($lower) {
            return $candidate instanceof User
                && str_contains(strtolower((string) $candidate->name), $lower);
        })->values();

        return $partial->count() === 1 ? $partial->first() : null;
    }

    /**
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function downloadTemplate(string $format = 'xlsx')
    {
        $users = $this->templateUsers();
        $sampleRows = $this->templateSampleRowsForUsers($users);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Tasks');
        $lastCol = Coordinate::stringFromColumnIndex(count(self::TEMPLATE_HEADERS));
        $sheet->fromArray(self::TEMPLATE_HEADERS, null, 'A1');
        $sheet->fromArray($sampleRows, null, 'A2');
        $sheet->getStyle('A1:'.$lastCol.'1')->getFont()->setBold(true);
        $sheet->getStyle('B1:E1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D1FAE5');
        $sheet->freezePane('A2');
        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $format = strtolower($format) === 'csv' ? 'csv' : 'xlsx';
        if ($format === 'csv') {
            $sheet->fromArray([[], ['Users — pick these exact Names. Email is only a reference.']], null, 'A5');
            $sheet->fromArray(['Name', 'Email'], null, 'A6');
            $sheet->fromArray($users->map(fn (User $user) => [$user->name, $user->email])->values()->all(), null, 'A7');

            $writer = new CsvWriter($spreadsheet);
            $writer->setDelimiter(',');
            $writer->setEnclosure('"');
            $writer->setLineEnding("\r\n");
            $filename = 'task_import_template.csv';
            $contentType = 'text/csv';
        } else {
            $this->addUsersSheet($spreadsheet, $users);
            $this->addTaskSheetDropdowns($sheet, $users->count());
            $spreadsheet->setActiveSheetIndexByName('Tasks');

            $writer = new Xlsx($spreadsheet);
            $filename = 'task_import_template.xlsx';
            $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        }

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => $contentType,
        ]);
    }

    /**
     * @return Collection<int, User>
     */
    public function templateUsers(): Collection
    {
        $query = User::query()->select('id', 'name', 'email')->orderBy('name');
        if (Schema::hasColumn('users', 'is_active')) {
            $query->where('is_active', true);
        }

        return $query->get()
            ->filter(fn (User $user) => trim((string) $user->name) !== '')
            ->unique(fn (User $user) => strtolower(trim((string) $user->name)))
            ->values();
    }

    /**
     * @param  Collection<int, User>  $users
     * @return list<list<string>>
     */
    private function templateSampleRowsForUsers(Collection $users): array
    {
        return self::templateSampleRows();
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function addUsersSheet(Spreadsheet $spreadsheet, Collection $users): void
    {
        $usersSheet = $spreadsheet->createSheet();
        $usersSheet->setTitle('Users');
        $usersSheet->fromArray(['Name', 'Email'], null, 'A1');
        $usersSheet->getStyle('A1:B1')->getFont()->setBold(true);
        $usersSheet->fromArray(
            $users->map(fn (User $user) => [$user->name, $user->email])->values()->all(),
            null,
            'A2'
        );
        $usersSheet->getColumnDimension('A')->setAutoSize(true);
        $usersSheet->getColumnDimension('B')->setAutoSize(true);
        $lastUserRow = max(2, $users->count() + 1);
        $spreadsheet->addNamedRange(new NamedRange('UserNames', $usersSheet, '$A$2:$A$'.$lastUserRow));

        $help = $spreadsheet->createSheet();
        $help->setTitle('How to fill');
        $help->fromArray([
            ['How to assign tasks in this sheet'],
            ['1. Stay on the Tasks tab.'],
            ['2. Click an Assignee cell and use the dropdown arrow to pick a person.'],
            ['3. The list is the exact Names from this system — do not type a different spelling.'],
            ['4. For more people, pick names in Assignee 2 and Assignee 3.'],
            ['5. Assignor is also a dropdown. Leave it blank to use yourself.'],
            ['6. Delete the sample rows before upload if you do not want those tasks created.'],
        ], null, 'A1');
        $help->getStyle('A1')->getFont()->setBold(true);
        $help->getColumnDimension('A')->setWidth(100);
    }

    private function addTaskSheetDropdowns(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $userCount): void
    {
        $nameList = $userCount > 0 ? '=UserNames' : '';
        if ($nameList !== '') {
            foreach (['B2:B200', 'C2:C200', 'D2:D200', 'E2:E200'] as $range) {
                $sheet->setDataValidation($range, $this->listValidation(
                    $nameList,
                    'Select a user',
                    'Pick the person from this list. Do not type a different name.'
                ));
            }
        }

        $sheet->setDataValidation('G2:G200', $this->listValidation(
            '"Low,Normal,High,Urgent"',
            'Priority',
            'Select Low, Normal, High, or Urgent.'
        ));
        $sheet->setDataValidation('H2:H200', $this->listValidation(
            '"Todo,Working,Done,Need Help,Need Approval,Dependent,Approved,Hold,Monitor,Rework,Cancelled"',
            'Status',
            'Select a status, or leave Todo.'
        ));
    }

    private function listValidation(string $formula, string $promptTitle, string $prompt): DataValidation
    {
        $validation = new DataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setShowDropDown(true);
        $validation->setFormula1($formula);
        $validation->setPromptTitle($promptTitle);
        $validation->setPrompt($prompt);
        $validation->setErrorTitle('Pick from the list');
        $validation->setError('Please select a value from the dropdown.');

        return $validation;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function readRows(UploadedFile $file): array
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $path = $file->getRealPath();
        if (! $path) {
            throw new \InvalidArgumentException('Could not read the uploaded file.');
        }

        $spreadsheetExts = ['xlsx', 'xls', 'xlsm', 'ods'];
        if (in_array($ext, $spreadsheetExts, true)) {
            $spreadsheet = IOFactory::load($path);
        } else {
            $reader = new CsvReader();
            $reader->setDelimiter($this->sniffDelimiter($path));
            $reader->setEnclosure('"');
            $reader->setSheetIndex(0);
            $reader->setInputEncoding(CsvReader::GUESS_ENCODING);
            $spreadsheet = $reader->load($path);
        }

        $sheet = $spreadsheet->getSheetByName('Tasks') ?? $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);
        while ($rows !== []) {
            $last = end($rows);
            $empty = true;
            foreach ((array) $last as $cell) {
                if ($cell !== null && trim((string) $cell) !== '') {
                    $empty = false;
                    break;
                }
            }
            if (! $empty) {
                break;
            }
            array_pop($rows);
        }

        return array_values($rows);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createAssignedTask(array $payload): Task
    {
        if (! Schema::hasColumn('tasks', 'is_corrective_action')) {
            unset($payload['is_corrective_action']);
        }
        if (array_key_exists('parent_task_id', $payload) && $payload['parent_task_id'] === null) {
            unset($payload['parent_task_id']);
        }
        if (Schema::hasColumn('tasks', 'screenshots') && ! empty($payload['screenshots'])) {
            $payload['screenshots'] = is_array($payload['screenshots'])
                ? json_encode($payload['screenshots'])
                : $payload['screenshots'];
        } else {
            unset($payload['screenshots']);
        }

        $this->ensureTasksIdAutoIncrement();
        $payload['id'] = $this->allocateTaskId();

        foreach (['start_date', 'completion_date', 'due_date'] as $dateKey) {
            if (($payload[$dateKey] ?? null) instanceof \DateTimeInterface) {
                $payload[$dateKey] = Carbon::parse($payload[$dateKey])->format('Y-m-d H:i:s');
            }
        }

        $now = now()->format('Y-m-d H:i:s');
        $payload['created_at'] = $now;
        $payload['updated_at'] = $now;

        DB::table('tasks')->insert($payload);

        return Task::query()->findOrFail($payload['id']);
    }

    private function allocateTaskId(): int
    {
        if ($this->nextTaskId === null) {
            $max = (int) DB::table('tasks')->max('id');
            if (Schema::hasTable('deleted_tasks')) {
                $max = max($max, (int) DB::table('deleted_tasks')->max('id'));
            }
            $this->nextTaskId = $max + 1;
        }

        return $this->nextTaskId++;
    }

    private function ensureTasksIdAutoIncrement(): void
    {
        static $checked = false;
        if ($checked || DB::getDriverName() !== 'mysql' || ! Schema::hasTable('tasks')) {
            $checked = true;

            return;
        }
        $checked = true;

        try {
            $idColumn = DB::selectOne("SHOW COLUMNS FROM `tasks` WHERE Field = 'id'");
            if ($idColumn && str_contains(strtolower((string) ($idColumn->Extra ?? '')), 'auto_increment')) {
                return;
            }
            $next = max(1, ((int) DB::table('tasks')->max('id')) + 1);
            DB::statement('ALTER TABLE `tasks` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
            DB::statement('ALTER TABLE `tasks` AUTO_INCREMENT = '.$next);
        } catch (\Throwable $e) {
            Log::warning('Could not restore AUTO_INCREMENT on tasks.id: '.$e->getMessage());
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    private function isExampleTemplateRow(array $row): bool
    {
        $title = strtolower(trim((string) ($row['title'] ?? '')));

        return in_array($title, [
            'review live listings',
            'update product titles',
            'sample task 1',
            'sample task 2',
        ], true);
    }

    private function publicRowError(\Throwable $e): string
    {
        $message = $e->getMessage();
        if (str_contains($message, "Field 'id' doesn't have a default value")) {
            return 'Could not save the task. Please upload again.';
        }
        if (str_contains($message, 'SQLSTATE')) {
            return 'Could not create this task. Check the row and try again.';
        }

        return $message;
    }

    /**
     * @param  list<string>  $warnings
     */
    private function notifyAssignees(Task $task, array &$warnings): void
    {
        $emails = array_values(array_filter(array_map('trim', explode(',', (string) $task->assign_to))));
        if ($emails === []) {
            return;
        }

        foreach ($emails as $email) {
            $notifyTask = $task;
            if (count($emails) > 1) {
                $notifyTask = clone $task;
                $notifyTask->assign_to = $email;
            }

            try {
                $status = $this->taskWhatsApp->notifyNewTaskAssigned($notifyTask);
                if ($status === 'skipped_no_phone') {
                    $warnings[] = 'WhatsApp not sent for '.$email.': no phone on user profile.';
                } elseif ($status === 'skipped_no_user') {
                    $warnings[] = 'WhatsApp not sent for '.$email.': user not found.';
                }
            } catch (\Throwable $e) {
                Log::warning('Task sheet WhatsApp notify failed: '.$e->getMessage(), [
                    'task_id' => $task->id,
                    'email' => $email,
                ]);
                $warnings[] = 'WhatsApp send failed for '.$email.'.';
            }
        }
    }

    /**
     * @param  list<string>  $header
     * @return array<int, string>
     */
    private function headerMap(array $header): array
    {
        $map = [];
        foreach ($header as $index => $label) {
            if ($label !== '' && isset(self::HEADER_ALIASES[$label])) {
                $map[$index] = self::HEADER_ALIASES[$label];
            }
        }

        return $map;
    }

    /**
     * @param  list<string>  $header
     * @param  list<string>  $values
     * @return array<string, string>
     */
    private function rowFromHeaders(array $header, array $values): array
    {
        $row = [];
        foreach ($header as $index => $label) {
            $key = self::HEADER_ALIASES[$label] ?? null;
            if ($key === null) {
                continue;
            }
            $row[$key] = $values[$index] ?? '';
        }

        return $row;
    }

    /**
     * @param  list<string>  $values
     * @return array<string, string>
     */
    private function rowFromPosition(array $values): array
    {
        $row = [];
        foreach (self::POSITIONAL_KEYS as $index => $key) {
            $row[$key] = $values[$index] ?? '';
        }

        return $row;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, string>
     */
    private function parseLinks(array $row): array
    {
        $links = [
            'l1' => trim((string) ($row['l1'] ?? '')),
            'l2' => trim((string) ($row['l2'] ?? '')),
            'training_link' => trim((string) ($row['training_link'] ?? '')),
            'video_link' => trim((string) ($row['video_link'] ?? '')),
            'form_link' => trim((string) ($row['form_link'] ?? '')),
            'form_report_link' => trim((string) ($row['form_report_link'] ?? '')),
            'checklist_link' => trim((string) ($row['checklist_link'] ?? '')),
            'pl' => trim((string) ($row['pl'] ?? '')),
            'process' => trim((string) ($row['process'] ?? '')),
        ];

        $blob = trim((string) ($row['links'] ?? ''));
        if ($blob !== '' && $links['l1'] === '') {
            if (preg_match('/L1:\s*(.+)/i', $blob, $matches)) {
                $links['l1'] = trim($matches[1]);
            } else {
                $links['l1'] = $blob;
            }
        }

        return $links;
    }

    public function mapPriority(?string $priority): string
    {
        $key = strtolower(trim((string) $priority));
        return match ($key) {
            'urgent', 'high' => 'high',
            'low' => 'low',
            default => 'normal',
        };
    }

    public function mapStatus(?string $status): string
    {
        $key = strtolower(trim((string) $status));
        $statusMap = [
            'todo' => 'Todo',
            'pending' => 'Todo',
            'working' => 'Working',
            'archived' => 'Archived',
            'done' => 'Done',
            'need help' => 'Need Help',
            'need approval' => 'Need Approval',
            'dependent' => 'Dependent',
            'approved' => 'Approved',
            'hold' => 'Hold',
            'monitor' => 'Monitor',
            'rework' => 'Rework',
            'cancelled' => 'Cancelled',
        ];

        return $statusMap[$key] ?? 'Todo';
    }

    /**
     * @return list<string>
     */
    public function splitPeople(string $raw): array
    {
        $parts = preg_split('/[;,]/', $raw) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn ($part) => $part !== ''));
    }

    /**
     * @param  array<string, string>  $row
     */
    public function combinedAssigneeRaw(array $row): string
    {
        $parts = [];
        foreach (['assignee', 'assignee_2', 'assignee_3'] as $key) {
            foreach ($this->splitPeople((string) ($row[$key] ?? '')) as $person) {
                $parts[] = $person;
            }
        }

        return implode(', ', array_values(array_unique($parts)));
    }

    private function parseStartDate(mixed $value): Carbon
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return now();
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException('Invalid start date: '.$raw);
        }
    }

    private function parseEtcMinutes(mixed $value): int
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return 10;
        }
        if (! is_numeric($raw) || (int) $raw < 1) {
            throw new \InvalidArgumentException('ETC minutes must be a positive number.');
        }

        return (int) $raw;
    }

    private function parseYesNo(mixed $value): int
    {
        $raw = strtolower(trim((string) ($value ?? '')));

        return in_array($raw, ['1', 'yes', 'y', 'true'], true) ? 1 : 0;
    }

    private function nullableString(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));

        return $raw === '' ? null : $raw;
    }

    private function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
        $header = strtolower(trim($header));
        $header = str_replace(['_', '-'], ' ', $header);
        $header = preg_replace('/\s+/', ' ', $header) ?? $header;

        return $header;
    }

    /**
     * @param  list<string>  $values
     */
    private function rowIsEmpty(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function sniffDelimiter(string $path): string
    {
        $sample = '';
        $handle = @fopen($path, 'r');
        if ($handle) {
            $sample = (string) fread($handle, 32768);
            fclose($handle);
        }

        $candidates = ["\t" => 0, ',' => 0, ';' => 0, '|' => 0];
        foreach ($candidates as $delimiter => $count) {
            $candidates[$delimiter] = substr_count($sample, $delimiter);
        }
        arsort($candidates);
        $top = array_key_first($candidates);

        return ($candidates[$top] ?? 0) > 0 ? $top : ',';
    }
}
