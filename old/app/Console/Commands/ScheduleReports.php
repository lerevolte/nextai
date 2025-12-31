<?
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ReportExportService;

class ScheduleReports extends Command
{
    protected $signature = 'report:schedule
                          {--list : List scheduled reports}
                          {--run : Run scheduled reports}
                          {--create : Create new scheduled report}';
    
    protected $description = 'Manage scheduled reports';

    protected ReportExportService $reportService;

    public function __construct(ReportExportService $reportService)
    {
        parent::__construct();
        $this->reportService = $reportService;
    }

    public function handle()
    {
        if ($this->option('list')) {
            $this->listScheduledReports();
        } elseif ($this->option('run')) {
            $this->runScheduledReports();
        } elseif ($this->option('create')) {
            $this->createScheduledReport();
        } else {
            $this->info('Please specify an option: --list, --run, or --create');
        }
        
        return 0;
    }
    
    protected function listScheduledReports()
    {
        $reports = \DB::table('scheduled_reports')
            ->join('organizations', 'scheduled_reports.organization_id', '=', 'organizations.id')
            ->select('scheduled_reports.*', 'organizations.name as org_name')
            ->where('scheduled_reports.is_active', true)
            ->get();
        
        if ($reports->isEmpty()) {
            $this->warn('No scheduled reports found.');
            return;
        }
        
        $this->table(
            ['ID', 'Organization', 'Name', 'Frequency', 'Format', 'Next Run'],
            $reports->map(function ($report) {
                return [
                    $report->id,
                    $report->org_name,
                    $report->name,
                    $report->frequency,
                    $report->format,
                    $report->next_run_at
                ];
            })
        );
    }
    
    protected function runScheduledReports()
    {
        $this->info('Running scheduled reports...');
        $this->reportService->runScheduledReports();
        $this->info('✅ Scheduled reports executed successfully!');
    }
    
    protected function createScheduledReport()
    {
        $orgId = $this->ask('Organization ID');
        $organization = \App\Models\Organization::find($orgId);
        
        if (!$organization) {
            $this->error('Organization not found.');
            return;
        }
        
        $name = $this->ask('Report name');
        $frequency = $this->choice('Frequency', ['daily', 'weekly', 'monthly'], 1);
        $format = $this->choice('Format', ['pdf', 'excel', 'csv'], 0);
        $recipients = $this->ask('Recipients (comma-separated emails)');
        
        $config = [
            'name' => $name,
            'frequency' => $frequency,
            'format' => $format,
            'recipients' => array_map('trim', explode(',', $recipients)),
            'options' => [
                'include_charts' => $this->confirm('Include charts?', true),
                'period' => $this->ask('Period in days', 30)
            ]
        ];
        
        $this->reportService->scheduleReport($organization, $config);
        
        $this->info('✅ Scheduled report created successfully!');
    }
}