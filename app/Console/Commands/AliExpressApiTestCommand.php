<?php

namespace App\Console\Commands;

use App\Services\AliExpressApiService;
use Illuminate\Console\Command;

class AliExpressApiTestCommand extends Command
{
    protected $signature = 'aliexpress:test
                            {action=list : list|edit|debug-list}
                            {--page=1 : Page for list}
                            {--page-size=5 : Page size for list}
                            {--product-id= : Product ID for edit}
                            {--title= : New title for edit}';

    protected $description = 'Test AliExpress REST API (solution product list / edit)';

    public function handle(AliExpressApiService $aliExpress): int
    {
        $action = strtolower($this->argument('action'));

        if ($action === 'list') {
            $page = (int) $this->option('page');
            $size = (int) $this->option('page-size');
            $this->info("Calling getInventory(page={$page}, page_size={$size})...");
            $out = $aliExpress->getInventory($page, $size);
            $this->line(json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return isset($out['success']) && $out['success'] ? self::SUCCESS : self::FAILURE;
        }

        if ($action === 'edit') {
            $pid = (string) $this->option('product-id');
            $title = (string) $this->option('title');
            if ($pid === '' || $title === '') {
                $this->error('Provide --product-id and --title');

                return self::FAILURE;
            }
            $this->info("Calling updateTitle({$pid}, ...)...");
            $out = $aliExpress->updateTitle($pid, $title);
            $this->line(json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return isset($out['success']) && $out['success'] ? self::SUCCESS : self::FAILURE;
        }

        if ($action === 'debug-list') {
            $page = (int) $this->option('page');
            $size = (int) $this->option('page-size');
            $req = $aliExpress->buildProductListRequest([
                'current_page' => $page,
                'page_size' => $size,
            ]);
            $encoded = json_encode($req, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $this->info('debugCallRest — inspect sign_source and response');
            $out = $aliExpress->debugCallRest('aliexpress.solution.product.list.get', [
                'product_list_get_request' => $encoded,
            ]);
            $this->line(json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->error('Unknown action. Use: list, edit, debug-list');

        return self::FAILURE;
    }
}
