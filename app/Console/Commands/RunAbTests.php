<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AbTestingService;

class RunAbTests extends Command
{
    protected $signature = 'ab:check {--complete : Complete tests that meet criteria}';
    protected $description = 'Check and manage A/B tests';

    protected AbTestingService $abTestingService;

    public function __construct(AbTestingService $abTestingService)
    {
        parent::__construct();
        $this->abTestingService = $abTestingService;
    }

    public function handle()
    {
        $this->info('Checking A/B tests...');
        
        if ($this->option('complete')) {
            $this->abTestingService->checkAndCompleteTests();
            $this->info('Completed tests that met criteria.');
        }
        
        // Показываем статус активных тестов
        $activeTests = \App\Models\AbTest::active()->with('variants')->get();
        
        if ($activeTests->isEmpty()) {
            $this->warn('No active A/B tests found.');
            return;
        }
        
        foreach ($activeTests as $test) {
            $this->info("\n📊 Test: {$test->name}");
            $this->line("Status: {$test->status}");
            $this->line("Traffic: {$test->traffic_percentage}%");
            
            $analysis = $this->abTestingService->analyzeTest($test);
            
            $this->table(
                ['Variant', 'Participants', 'Conversion Rate', 'Statistical Significance'],
                collect($analysis['variants'])->map(function ($variant) {
                    return [
                        $variant['name'] . ($variant['is_control'] ? ' (Control)' : ''),
                        $variant['participants'],
                        $variant['conversion_rate'] . '%',
                        $variant['statistical_significance'] ? $variant['statistical_significance'] . '%' : 'N/A'
                    ];
                })
            );
            
            if ($analysis['winner']) {
                $this->info("🏆 Current winner: {$analysis['winner']['name']} (+{$analysis['winner']['improvement']}%)");
            }
        }
    }
}