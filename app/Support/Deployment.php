<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * The website's side of "Deploy latest". It only reads and writes small files; it never runs a
 * command. scripts/cpanel-deploy-runner.sh, started by cron on the server, does the work.
 *
 * Files in config('pool.deploy.path'):
 *   request    written here, taken by the runner
 *   status     key=value lines written by the runner
 *   heartbeat  touched by the runner on every start
 *   last.log   output of the last deployment
 *   lock/      exists while the runner is deploying
 */
class Deployment
{
    /**
     * When cron last started the runner.
     */
    public function cronSeenAt(): ?CarbonImmutable
    {
        $file = $this->path('heartbeat');
        clearstatcache(true, $file);

        return is_file($file) ? CarbonImmutable::createFromTimestamp((int) filemtime($file)) : null;
    }

    public function cronIsActive(): bool
    {
        $seen = $this->cronSeenAt();

        // While deploying, the runner is busy and later starts only refresh the heartbeat.
        return $seen !== null && $seen->gt(now()->subMinutes((int) config('pool.deploy.cron_stale_minutes')));
    }

    /**
     * The request that is waiting for cron, if there is one.
     *
     * @return array{requested_by: string, requested_at: string}|null
     */
    public function pending(): ?array
    {
        $file = $this->path('request');
        clearstatcache(true, $file);

        if (! is_file($file)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($file), true);

        return [
            'requested_by' => (string) ($data['requested_by'] ?? ''),
            'requested_at' => (string) ($data['requested_at'] ?? ''),
        ];
    }

    public function isRunning(): bool
    {
        clearstatcache(true, $this->path('lock'));

        return is_dir($this->path('lock'));
    }

    /**
     * Ask for a deployment. False when one is already waiting or running.
     */
    public function request(User $admin): bool
    {
        if ($this->isRunning()) {
            return false;
        }

        File::ensureDirectoryExists($this->path());

        // "x" creates the file only if it does not exist, so two clicks cannot both succeed.
        $handle = @fopen($this->path('request'), 'x');

        if ($handle === false) {
            return false;
        }

        fwrite($handle, (string) json_encode([
            'requested_by' => $admin->name,
            'requested_at' => now()->toIso8601String(),
        ]));
        fclose($handle);

        Log::warning('Deployment requested from the dashboard.', ['user_id' => $admin->id]);

        return true;
    }

    /**
     * Withdraw a request the runner has not taken yet.
     */
    public function cancel(): void
    {
        File::delete($this->path('request'));
    }

    /**
     * The state of the last deployment the runner started.
     *
     * @return array<string, string>|null
     */
    public function lastRun(): ?array
    {
        $file = $this->path('status');

        if (! is_file($file)) {
            return null;
        }

        $status = [];

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $status[$key] = $value;
        }

        // It says "running", but nothing is: the runner was killed or the server restarted.
        if (($status['state'] ?? '') === 'running' && ! $this->isRunning()) {
            $status['state'] = 'failed';
            $status['message'] = 'The deployment stopped before it finished.';
        }

        return $status;
    }

    /**
     * The end of the last deployment's output.
     */
    public function logTail(int $lines = 80): string
    {
        $file = $this->path('last.log');

        if (! is_file($file)) {
            return '';
        }

        $text = (string) file_get_contents($file, offset: max(0, (int) filesize($file) - 20000));
        // Remove terminal colour codes and anything that is not valid text.
        $text = (string) preg_replace('/\e\[[0-9;]*m/', '', mb_scrub($text));

        return trim(implode("\n", array_slice(explode("\n", $text), -$lines)));
    }

    /**
     * The short hash of the code on this server, read from the git files.
     */
    public function currentCommit(): ?string
    {
        $git = rtrim((string) config('pool.deploy.git_path'), '/');
        $head = is_file("{$git}/HEAD") ? trim((string) file_get_contents("{$git}/HEAD")) : '';

        if (str_starts_with($head, 'ref: ')) {
            $ref = substr($head, 5);
            $head = '';

            if (is_file("{$git}/{$ref}")) {
                $head = trim((string) file_get_contents("{$git}/{$ref}"));
            } elseif (is_file("{$git}/packed-refs")
                && preg_match('/^([0-9a-f]{40}) '.preg_quote($ref, '/').'$/m', (string) file_get_contents("{$git}/packed-refs"), $match) === 1) {
                $head = $match[1];
            }
        }

        return preg_match('/^[0-9a-f]{40}$/', $head) === 1 ? substr($head, 0, 7) : null;
    }

    private function path(string $file = ''): string
    {
        return rtrim((string) config('pool.deploy.path'), '/').($file === '' ? '' : "/{$file}");
    }
}
