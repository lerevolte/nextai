<?
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReportsTables extends Migration
{
    public function up()
    {
        // Запланированные отчеты
        Schema::create('scheduled_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'quarterly']);
            $table->enum('format', ['pdf', 'excel', 'csv', 'json']);
            $table->json('recipients'); // Email адреса получателей
            $table->json('config'); // Конфигурация отчета
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();
            
            $table->index(['organization_id', 'is_active']);
            $table->index('next_run_at');
        });

        // История сгенерированных отчетов
        Schema::create('generated_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('scheduled_report_id')->nullable()->constrained()->onDelete('set null');
            $table->string('name');
            $table->string('format');
            $table->string('file_path');
            $table->integer('file_size');
            $table->json('parameters'); // Параметры генерации
            $table->json('metrics_snapshot'); // Снимок метрик на момент генерации
            $table->timestamp('generated_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            
            $table->index(['organization_id', 'generated_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('generated_reports');
        Schema::dropIfExists('scheduled_reports');
    }
}