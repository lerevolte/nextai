<?
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ReportExportService;
use App\Models\Organization;

class GenerateReport extends Command
{
    protected $signature = 'report:generate 
                          {organization : Organization ID or slug}
                          {--format=pdf : Report format (pdf, excel, csv, json)}
                          {--period=30 : Period in days}
                          {--email= : Email to send report}
                          {--bot= : Specific bot ID}';
    
    protected $description = 'Generate analytics report';

    protected ReportExportService $reportService;

    public function __construct(ReportExportService $reportService)
    {
        parent::__construct();
        $this->reportService = $reportService;
    }

    public function handle()
    {
        $organization = is_numeric($this->argument('organization'))
            ? Organization::find($this->argument('organization'))
            : Organization::where('slug', $this->argument('organization'))->first();
        
        if (!$organization) {
            $this->error('Organization not found.');
            return 1;
        }
        
        $this->info("Generating {$this->option('format')} report for {$organization->name}...");
        
        $options = [
            'format' => $this->option('format'),
            'period' => $this->option('period'),
            'bot_id' => $this->option('bot'),
            'include_charts' => true
        ];
        
        try {
            $url = $this->reportService->generateReport($organization, $options);
            
            $this->info("✅ Report generated successfully!");
            $this->line("URL: {$url}");
            
            if ($email = $this->option('email')) {
                // Отправляем на email
                \Mail::to($email)->send(new \App\Mail\ReportGenerated($url, $organization));
                $this->info("Report sent to {$email}");
            }
            
        } catch (\Exception $e) {
            $this->error("Failed to generate report: {$e->getMessage()}");
            return 1;
        }
        
        return 0;
    }
}