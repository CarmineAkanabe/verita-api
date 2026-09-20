<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('demo:reset {--force : Skip the confirmation prompt}')]
#[Description('Wipe and reseed the database with a full demo dataset. No AI calls, no queue worker required.')]
class DemoReset extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (! $this->option('force') && ! $this->confirm('This will drop and rebuild the entire database. Continue?')) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        $this->call('migrate:fresh', ['--seed' => true]);
        $this->info('Demo data reset — 5 cases across SUBMITTED/AWAITING_REVIEW/UNDER_INVESTIGATION/RESOLVED/DISMISSED.');

        return self::SUCCESS;
    }
}
