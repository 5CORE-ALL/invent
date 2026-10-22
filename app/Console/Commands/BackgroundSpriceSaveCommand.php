<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Support\BackgroundSpriceSave;
use Illuminate\Console\Command;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Detached worker for "Save and Apply" (see BackgroundSpriceSave). Replays the browser's S PRC writes
 * against the channel's own save route, as the user who clicked, so closing the page does not stop it.
 */
class BackgroundSpriceSaveCommand extends Command
{
    protected $signature = 'sprice:background-save {id : Payload id written by BackgroundSpriceSave::enqueue}';

    protected $description = 'Write S PRC values handed over by a pricing page (Save and Apply) in the background.';

    public function handle(): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $id = (string) $this->argument('id');
        if (! BackgroundSpriceSave::isValidId($id)) {
            $this->error('Invalid id');

            return self::FAILURE;
        }
        $file = BackgroundSpriceSave::payloadPath($id);
        $payload = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (! is_array($payload)) {
            $this->error('Payload not found');

            return self::FAILURE;
        }

        $path = (string) ($payload['path'] ?? '');
        $updates = is_array($payload['updates'] ?? null) ? $payload['updates'] : [];
        $extra = is_array($payload['extra'] ?? null) ? $payload['extra'] : [];
        $single = ($payload['mode'] ?? 'batch') === 'single';
        $label = (string) ($payload['label'] ?? '');
        $total = count($updates);

        $status = [
            'id' => $id,
            'state' => 'running',
            'label' => $label,
            'path' => $path,
            'total' => $total,
            'done' => 0,
            'failed' => 0,
            'message' => 'Waiting for earlier saves on this page to finish…',
        ];
        BackgroundSpriceSave::writeStatus($id, $status);

        // One writer per save route: a later Save and Apply waits for the earlier one.
        $lock = @fopen(BackgroundSpriceSave::lockPath($path), 'c+');
        if ($lock) {
            flock($lock, LOCK_EX);
        }

        try {
            $user = User::query()->find((int) ($payload['user_id'] ?? 0));
            if (! $user) {
                throw new \RuntimeException('User not found');
            }
            Auth::setUser($user);

            $status['message'] = 'Saving S PRC…';
            BackgroundSpriceSave::writeStatus($id, $status);

            $size = $single ? 1 : 200;
            foreach (array_chunk($updates, $size) as $chunk) {
                $data = $single
                    ? array_merge($extra, (array) $chunk[0])
                    : array_merge($extra, ['updates' => $chunk]);
                $ok = $this->replay($path, $data, $user);
                if ($ok) {
                    $status['done'] += count($chunk);
                } else {
                    $status['failed'] += count($chunk);
                }
                if ($single ? ($status['done'] + $status['failed']) % 25 === 0 : true) {
                    BackgroundSpriceSave::writeStatus($id, $status);
                }
            }

            $status['state'] = $status['failed'] > 0 && $status['done'] === 0 ? 'failed' : 'done';
            $status['message'] = 'Saved '.$status['done'].' of '.$total.($status['failed'] ? (', '.$status['failed'].' failed') : '');
        } catch (\Throwable $e) {
            $status['state'] = 'failed';
            $status['message'] = $e->getMessage();
            Log::error('Background S PRC save failed', ['id' => $id, 'path' => $path, 'error' => $e->getMessage()]);
        } finally {
            if ($lock) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }

        BackgroundSpriceSave::writeStatus($id, $status);
        @unlink($file);
        $this->info($status['message']);
        Log::info('Background S PRC save finished', $status);

        return $status['state'] === 'failed' ? self::FAILURE : self::SUCCESS;
    }

    /** @param  array<string, mixed>  $data */
    private function replay(string $path, array $data, User $user): bool
    {
        try {
            $request = Request::create($path, 'POST', $data, [], [], [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            ]);
            $request->setUserResolver(static fn () => $user);
            $router = app('router');
            $route = $router->getRoutes()->match($request);
            $request->setRouteResolver(static fn () => $route);
            app()->instance('request', $request);

            $response = $route->run();
            $response = $router->prepareResponse($request, $response);

            if ($response->getStatusCode() >= 400) {
                return false;
            }
            if ($response instanceof JsonResponse) {
                $body = $response->getData(true);
                if (is_array($body) && array_key_exists('success', $body) && $body['success'] === false) {
                    return false;
                }
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Background S PRC save request failed', ['path' => $path, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
