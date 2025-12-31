<?php

namespace App\Services\Importers;

use Illuminate\Http\UploadedFile;
use Smalot\PdfParser\Parser as PdfParser;
use PhpOffice\PhpWord\IOFactory as WordFactory;
use PhpOffice\PhpSpreadsheet\IOFactory as ExcelFactory;
use League\HTMLToMarkdown\HtmlConverter;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeItem;

class DocumentImporter
{
    protected HtmlConverter $htmlConverter;
    protected PdfParser $pdfParser;

    public function __construct()
    {
        $this->htmlConverter = new HtmlConverter();
        $this->pdfParser = new PdfParser();
    }

    public function import(UploadedFile $file, KnowledgeBase $knowledgeBase): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        
        switch ($extension) {
            case 'pdf':
                return $this->importPdf($file, $knowledgeBase);
                
            case 'doc':
            case 'docx':
                return $this->importWord($file, $knowledgeBase);
                
            case 'xls':
            case 'xlsx':
                return $this->importExcel($file, $knowledgeBase);
                
            case 'txt':
            case 'md':
                return $this->importText($file, $knowledgeBase);
                
            case 'html':
                return $this->importHtml($file, $knowledgeBase);
                
            case 'csv':
                return $this->importCsv($file, $knowledgeBase);
                
            default:
                throw new \Exception('Неподдерживаемый формат файла: ' . $extension);
        }
    }

    protected function importPdf(UploadedFile $file, KnowledgeBase $knowledgeBase): array
    {
        $pdf = $this->pdfParser->parseFile($file->getRealPath());
        $text = $pdf->getText();
        
        if (empty($text)) {
            throw new \Exception('Не удалось извлечь текст из PDF');
        }

        // Разбиваем на страницы или секции
        $pages = $pdf->getPages();
        $items = [];
        
        foreach ($pages as $index => $page) {
            $pageText = $page->getText();
            
            if (strlen($pageText) < 100) {
                continue; // Пропускаем почти пустые страницы
            }
            
            $items[] = $this->createKnowledgeItem(
                $knowledgeBase,
                $file->getClientOriginalName() . ' - Страница ' . ($index + 1),
                $this->cleanText($pageText),
                'file',
                ['source_file' => $file->getClientOriginalName(), 'page' => $index + 1]
            );
        }

        return $items;
    }

    protected function importWord(UploadedFile $file, KnowledgeBase $knowledgeBase): array
    {
        $phpWord = WordFactory::load($file->getRealPath());
        $html = '';
        
        // Конвертируем в HTML
        $htmlWriter = WordFactory::createWriter($phpWord, 'HTML');
        $tempFile = tempnam(sys_get_temp_dir(), 'word');
        $htmlWriter->save($tempFile);
        $html = file_get_contents($tempFile);
        unlink($tempFile);
        
        // Конвертируем HTML в Markdown
        $markdown = $this->htmlConverter->convert($html);
        
        // Разбиваем по заголовкам
        $sections = $this->splitByHeaders($markdown);
        $items = [];
        
        foreach ($sections as $index => $section) {
            if (strlen($section['content']) < 100) {
                continue;
            }
            
            $items[] = $this->createKnowledgeItem(
                $knowledgeBase,
                $section['title'] ?: ($file->getClientOriginalName() . ' - Секция ' . ($index + 1)),
                $section['content'],
                'file',
                ['source_file' => $file->getClientOriginalName(), 'section' => $index + 1]
            );
        }

        return $items;
    }

    protected function importExcel(UploadedFile $file, KnowledgeBase $knowledgeBase): array
    {
        $spreadsheet = ExcelFactory::load($file->getRealPath());
        $items = [];
        
        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $worksheetTitle = $worksheet->getTitle();
            $data = $worksheet->toArray();
            
            // Предполагаем, что первая строка - заголовки
            $headers = array_shift($data);
            
            // Создаем элементы для каждой строки или группы строк
            $content = "## Данные из листа: $worksheetTitle\n\n";
            
            foreach ($data as $row) {
                $rowContent = '';
                foreach ($headers as $index => $header) {
                    if (isset($row[$index]) && !empty($row[$index])) {
                        $rowContent .= "**$header:** {$row[$index]}\n";
                    }
                }
                
                if (!empty($rowContent)) {
                    $content .= $rowContent . "\n---\n\n";
                }
            }
            
            if (strlen($content) > 200) {
                $items[] = $this->createKnowledgeItem(
                    $knowledgeBase,
                    $file->getClientOriginalName() . ' - ' . $worksheetTitle,
                    $content,
                    'file',
                    ['source_file' => $file->getClientOriginalName(), 'worksheet' => $worksheetTitle]
                );
            }
        }

        return $items;
    }

    protected function importText(UploadedFile $file, KnowledgeBase $knowledgeBase): array
    {
        $content = file_get_contents($file->getRealPath());
        
        // Определяем кодировку и конвертируем в UTF-8
        $encoding = mb_detect_encoding($content, ['UTF-8', 'Windows-1251', 'CP1251', 'ISO-8859-1'], true);
        if ($encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }
        
        // Разбиваем на секции
        $sections = $this->splitTextIntoSections($content);
        $items = [];
        
        foreach ($sections as $index => $section) {
            if (strlen($section) < 100) {
                continue;
            }
            
            $items[] = $this->createKnowledgeItem(
                $knowledgeBase,
                $file->getClientOriginalName() . ' - Часть ' . ($index + 1),
                $section,
                'file',
                ['source_file' => $file->getClientOriginalName(), 'part' => $index + 1]
            );
        }

        return $items;
    }

    protected function importHtml(UploadedFile $file, KnowledgeBase $knowledgeBase): array
    {
        $html = file_get_contents($file->getRealPath());
        
        // Удаляем скрипты и стили
        $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $html);
        $html = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $html);
        
        // Конвертируем в Markdown
        $markdown = $this->htmlConverter->convert($html);
        
        // Разбиваем по заголовкам
        $sections = $this->splitByHeaders($markdown);
        $items = [];
        
        foreach ($sections as $index => $section) {
            if (strlen($section['content']) < 100) {
                continue;
            }
            
            $items[] = $this->createKnowledgeItem(
                $knowledgeBase,
                $section['title'] ?: ($file->getClientOriginalName() . ' - Секция ' . ($index + 1)),
                $section['content'],
                'file',
                ['source_file' => $file->getClientOriginalName()]
            );
        }

        return $items;
    }

    protected function importCsv(UploadedFile $file, KnowledgeBase $knowledgeBase): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        $headers = fgetcsv($handle);
        $items = [];
        $batchContent = '';
        $batchIndex = 1;
        $rowsPerBatch = 50;
        $currentRows = 0;
        
        while (($row = fgetcsv($handle)) !== false) {
            $rowContent = '';
            foreach ($headers as $index => $header) {
                if (isset($row[$index]) && !empty($row[$index])) {
                    $rowContent .= "**$header:** {$row[$index]}\n";
                }
            }
            
            if (!empty($rowContent)) {
                $batchContent .= $rowContent . "\n---\n\n";
                $currentRows++;
                
                if ($currentRows >= $rowsPerBatch) {
                    $items[] = $this->createKnowledgeItem(
                        $knowledgeBase,
                        $file->getClientOriginalName() . ' - Batch ' . $batchIndex,
                        $batchContent,
                        'file',
                        ['source_file' => $file->getClientOriginalName(), 'batch' => $batchIndex]
                    );
                    
                    $batchContent = '';
                    $currentRows = 0;
                    $batchIndex++;
                }
            }
        }
        
        // Сохраняем последний батч
        if (!empty($batchContent)) {
            $items[] = $this->createKnowledgeItem(
                $knowledgeBase,
                $file->getClientOriginalName() . ' - Batch ' . $batchIndex,
                $batchContent,
                'file',
                ['source_file' => $file->getClientOriginalName(), 'batch' => $batchIndex]
            );
        }
        
        fclose($handle);
        return $items;
    }

    protected function splitByHeaders(string $markdown): array
    {
        $sections = [];
        $currentSection = ['title' => '', 'content' => ''];
        
        $lines = explode("\n", $markdown);
        
        foreach ($lines as $line) {
            if (preg_match('/^#{1,3}\s+(.+)$/', $line, $matches)) {
                // Сохраняем предыдущую секцию
                if (!empty($currentSection['content'])) {
                    $sections[] = $currentSection;
                }
                
                // Начинаем новую секцию
                $currentSection = [
                    'title' => $matches[1],
                    'content' => '',
                ];
            } else {
                $currentSection['content'] .= $line . "\n";
            }
        }
        
        // Добавляем последнюю секцию
        if (!empty($currentSection['content'])) {
            $sections[] = $currentSection;
        }
        
        return $sections;
    }

    protected function splitTextIntoSections(string $text, int $maxLength = 3000): array
    {
        $sections = [];
        $paragraphs = preg_split('/\n\n+/', $text);
        $currentSection = '';
        
        foreach ($paragraphs as $paragraph) {
            if (strlen($currentSection) + strlen($paragraph) > $maxLength && !empty($currentSection)) {
                $sections[] = trim($currentSection);
                $currentSection = '';
            }
            
            $currentSection .= $paragraph . "\n\n";
        }
        
        if (!empty($currentSection)) {
            $sections[] = trim($currentSection);
        }
        
        return $sections;
    }

    protected function cleanText(string $text): string
    {
        // Удаляем лишние пробелы и переводы строк
        $text = preg_replace('/\s+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        
        // Удаляем спецсимволы
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        
        return trim($text);
    }

    protected function createKnowledgeItem(
        KnowledgeBase $knowledgeBase,
        string $title,
        string $content,
        string $type,
        array $metadata = []
    ): KnowledgeItem {
        $item = KnowledgeItem::create([
            'knowledge_base_id' => $knowledgeBase->id,
            'type' => $type,
            'title' => $title,
            'content' => $content,
            'is_active' => true,
            'metadata' => array_merge($metadata, [
                'imported_at' => now()->toIso8601String(),
                'imported_by' => auth()->id(),
            ]),
        ]);

        // Генерируем эмбеддинг в фоне
        dispatch(function () use ($item) {
            $embeddingService = app(\App\Services\EmbeddingService::class);
            $text = $item->title . "\n\n" . $item->content;
            $embedding = $embeddingService->generateEmbedding($text);
            
            if ($embedding) {
                $item->update(['embedding' => json_encode($embedding)]);
            }
        })->afterResponse();

        return $item;
    }
}