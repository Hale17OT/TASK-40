<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class RotateEncryptionKeyCommand extends Command
{
    protected $signature = 'harborbite:rotate-key {--dry-run : Show what would be re-encrypted without changes}';
    protected $description = 'Re-encrypt all sensitive fields with the current APP_KEY';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $this->info($isDryRun ? 'DRY RUN — no changes will be made.' : 'Starting key rotation...');

        $tables = [
            ['table' => 'device_fingerprints', 'columns' => ['user_agent', 'screen_traits']],
            ['table' => 'payment_confirmations', 'columns' => ['notes']],
            ['table' => 'incident_tickets', 'columns' => ['receipt_reference']],
            ['table' => 'cart_items', 'columns' => ['note']],
            ['table' => 'order_items', 'columns' => ['note']],
        ];

        $totalRows = 0;

        foreach ($tables as $config) {
            $tableName = $config['table'];
            $columns = $config['columns'];

            $rows = DB::table($tableName)->get();
            $this->line("  Processing {$tableName}: {$rows->count()} rows");

            foreach ($rows as $row) {
                $updates = [];
                foreach ($columns as $col) {
                    $value = $row->{$col};
                    if ($value === null) {
                        continue;
                    }

                    try {
                        // Try to decrypt with current key
                        $decrypted = Crypt::decryptString($value);
                        // Re-encrypt (will use current APP_KEY)
                        $updates[$col] = Crypt::encryptString($decrypted);
                    } catch (\Throwable $e) {
                        $this->warn("    Could not decrypt {$tableName}.{$col} row {$row->id}: {$e->getMessage()}");
                    }
                }

                if (!empty($updates) && !$isDryRun) {
                    DB::table($tableName)->where('id', $row->id)->update($updates);
                    $totalRows++;
                }
            }
        }

        $verb = $isDryRun ? 'Would re-encrypt' : 'Re-encrypted';
        $this->info("{$verb} {$totalRows} rows.");

        if (!$isDryRun) {
            Log::info('key_rotation_completed', ['rows_affected' => $totalRows]);
        }

        return self::SUCCESS;
    }
}
