<?php

namespace App\Console\Commands;

use App\Services\ScheduledTriggerService;
use Illuminate\Console\Command;

class RunScheduledFunctions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'functions:run-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and run scheduled functions';

    /**
     * Execute the console command.
     */
    public function handle(ScheduledTriggerService $service): int
    {
        $this->info('Checking scheduled functions...');
        
        try {
            $service->checkAndExecuteScheduledFunctions();
            $this->info('Scheduled functions check completed.');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error running scheduled functions: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}