<?php

namespace App\Services\Integrations;

use App\Models\KnowledgeSource;
use App\Models\KnowledgeItem;
use Google\Client;
use Google\Service\Docs;
use Google\Service\Drive;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleDocsService
{
    protected ?Client $client = null;
    protected ?Docs $docsService = null;
    protected ?Drive $driveService = null;
    protected bool $usePublicAccess = false;

    public function connect(array $config): bool
    {
        // Проверяем, используется ли публичный доступ
        if (($config['auth_type'] ?? '') === 'public') {
            $this->usePublicAccess = true;
            return true;
        }

        try {
            $this->client = new Client();
            $this->client->setApplicationName('Knowledge Base Sync');
            $this->client->setScopes([
                Docs::DOCUMENTS_READONLY,
                Drive::DRIVE_READONLY,
            ]);

            // Поддержка двух вариантов авторизации
            if (!empty($config['service_account_json'])) {
                $credentials = json_decode($config['service_account_json'], true);
                $this->client->setAuthConfig($credentials);
            } elseif (!empty($config['access_token'])) {
                $this->client->setAccessToken($config['access_token']);
                
                if ($this->client->isAccessTokenExpired() && !empty($config['refresh_token'])) {
                    $this->client->fetchAccessTokenWithRefreshToken($config['refresh_token']);
                }
            } else {
                throw new \Exception('No valid credentials provided');
            }

            $this->docsService = new Docs($this->client);
            $this->driveService = new Drive($this->client);

            return true;
        } catch (\Exception $e) {
            Log::error('Google Docs connection failed: ' . $e->getMessage());
            return false;
        }
    }

    public function syncDocuments(KnowledgeSource $source, ?int $userId = null): array
    {
        $config = $source->config;
        
        if (!$this->connect($config)) {
            throw new \Exception('Failed to connect to Google Docs');
        }

        $stats = [
            'added' => 0,
            'updated' => 0,
            'deleted' => 0,
            'errors' => [],
        ];

        $processedIds = [];
        $documentIds = $this->getDocumentIds($config);

        foreach ($documentIds as $docId) {
            try {
                $processedIds[] = $docId;
                $result = $this->syncDocument($source, $docId, $userId);
                
                if ($result === 'added') {
                    $stats['added']++;
                } elseif ($result === 'updated') {
                    $stats['updated']++;
                }
            } catch (\Exception $e) {
                $stats['errors'][] = [
                    'document_id' => $docId,
                    'error' => $e->getMessage(),
                ];
                Log::error('Failed to sync Google Doc', [
                    'doc_id' => $docId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Удаляем элементы, которых больше нет
        if ($config['delete_removed'] ?? false) {
            $deletedCount = KnowledgeItem::where('knowledge_source_id', $source->id)
                ->whereNotIn('external_id', $processedIds)
                ->delete();
            $stats['deleted'] = $deletedCount;
        }

        return $stats;
    }

    protected function getDocumentIds(array $config): array
    {
        $documentIds = [];

        // Вариант 1: Список конкретных документов
        if (!empty($config['document_ids'])) {
            return array_filter(array_map('trim', $config['document_ids']));
        }

        // Вариант 2: Все документы из папки (только с авторизацией)
        if (!empty($config['folder_id']) && !$this->usePublicAccess) {
            $documentIds = $this->getDocumentsFromFolder($config['folder_id']);
        }

        // Вариант 3: Документы по URL
        if (!empty($config['document_urls'])) {
            foreach ($config['document_urls'] as $url) {
                $docId = $this->extractDocumentId($url);
                if ($docId) {
                    $documentIds[] = $docId;
                }
            }
        }

        return array_unique($documentIds);
    }

    protected function getDocumentsFromFolder(string $folderId): array
    {
        if ($this->usePublicAccess) {
            return [];
        }

        $documentIds = [];
        $pageToken = null;

        do {
            $response = $this->driveService->files->listFiles([
                'q' => "'{$folderId}' in parents and mimeType='application/vnd.google-apps.document' and trashed=false",
                'pageSize' => 100,
                'pageToken' => $pageToken,
                'fields' => 'nextPageToken, files(id, name)',
            ]);

            foreach ($response->getFiles() as $file) {
                $documentIds[] = $file->getId();
            }

            $pageToken = $response->getNextPageToken();
        } while ($pageToken);

        return $documentIds;
    }

    protected function syncDocument(KnowledgeSource $source, string $docId, ?int $userId): string
    {
        if ($this->usePublicAccess) {
            return $this->syncPublicDocument($source, $docId, $userId);
        }

        return $this->syncPrivateDocument($source, $docId, $userId);
    }

    /**
     * Синхронизация публичного документа (без авторизации)
     */
    protected function syncPublicDocument(KnowledgeSource $source, string $docId, ?int $userId): string
    {
        // Получаем документ в текстовом формате
        $textUrl = "https://docs.google.com/document/d/{$docId}/export?format=txt";
        $htmlUrl = "https://docs.google.com/document/d/{$docId}/export?format=html";

        $textResponse = Http::timeout(30)->get($textUrl);
        
        if (!$textResponse->successful()) {
            throw new \Exception("Не удалось получить документ. Убедитесь, что доступ по ссылке разрешён. HTTP: " . $textResponse->status());
        }

        $content = $textResponse->body();
        
        // Пробуем получить заголовок из HTML версии
        $title = $this->extractTitleFromPublicDoc($docId, $htmlUrl);
        
        if (empty($title)) {
            $title = $this->extractTitleFromContent($content) ?? "Google Doc {$docId}";
        }

        $contentHash = md5($content);

        $item = KnowledgeItem::where('knowledge_source_id', $source->id)
            ->where('external_id', $docId)
            ->first();

        $metadata = [
            'google_doc_id' => $docId,
            'google_doc_url' => "https://docs.google.com/document/d/{$docId}/edit",
            'access_type' => 'public',
            'content_hash' => $contentHash,
            'synced_at' => now()->toIso8601String(),
        ];

        if ($item) {
            $oldHash = $item->sync_metadata['content_hash'] ?? '';
            
            if ($oldHash !== $contentHash) {
                $this->saveVersion($item, $userId);
                
                $item->update([
                    'title' => $title,
                    'content' => $content,
                    'version' => $item->version + 1,
                    'last_synced_at' => now(),
                    'sync_metadata' => $metadata,
                ]);
                
                $this->generateEmbedding($item);
                return 'updated';
            }
            
            return 'unchanged';
        }

        $item = KnowledgeItem::create([
            'knowledge_base_id' => $source->knowledge_base_id,
            'knowledge_source_id' => $source->id,
            'type' => 'google_docs',
            'title' => $title,
            'content' => $content,
            'external_id' => $docId,
            'source_url' => "https://docs.google.com/document/d/{$docId}/edit",
            'is_active' => true,
            'last_synced_at' => now(),
            'sync_metadata' => $metadata,
        ]);

        $this->generateEmbedding($item);
        return 'added';
    }

    /**
     * Извлекаем заголовок из публичного документа
     */
    protected function extractTitleFromPublicDoc(string $docId, string $htmlUrl): ?string
    {
        try {
            $response = Http::timeout(15)->get($htmlUrl);
            
            if ($response->successful()) {
                $html = $response->body();
                
                // Ищем title
                if (preg_match('/<title>([^<]+)<\/title>/i', $html, $matches)) {
                    $title = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
                    // Убираем " - Google Docs" из конца
                    $title = preg_replace('/\s*-\s*Google Docs\s*$/i', '', $title);
                    return trim($title);
                }
            }
        } catch (\Exception $e) {
            Log::warning("Could not extract title for doc {$docId}: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Извлекаем заголовок из первой строки контента
     */
    protected function extractTitleFromContent(string $content): ?string
    {
        $lines = explode("\n", trim($content));
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line) && mb_strlen($line) <= 200) {
                return $line;
            }
        }

        return null;
    }

    /**
     * Синхронизация приватного документа (с API)
     */
    protected function syncPrivateDocument(KnowledgeSource $source, string $docId, ?int $userId): string
    {
        $document = $this->docsService->documents->get($docId);
        
        $title = $document->getTitle();
        $content = $this->extractContent($document);
        $contentHash = md5($content);

        $item = KnowledgeItem::where('knowledge_source_id', $source->id)
            ->where('external_id', $docId)
            ->first();

        $metadata = [
            'google_doc_id' => $docId,
            'google_doc_url' => "https://docs.google.com/document/d/{$docId}/edit",
            'last_modified' => $document->getRevisionId(),
            'access_type' => 'api',
            'content_hash' => $contentHash,
        ];

        if ($item) {
            $oldHash = $item->sync_metadata['content_hash'] ?? '';
            
            if ($oldHash !== $contentHash) {
                $this->saveVersion($item, $userId);
                
                $item->update([
                    'title' => $title,
                    'content' => $content,
                    'version' => $item->version + 1,
                    'last_synced_at' => now(),
                    'sync_metadata' => $metadata,
                ]);
                
                $this->generateEmbedding($item);
                return 'updated';
            }
            
            return 'unchanged';
        }

        $item = KnowledgeItem::create([
            'knowledge_base_id' => $source->knowledge_base_id,
            'knowledge_source_id' => $source->id,
            'type' => 'google_docs',
            'title' => $title,
            'content' => $content,
            'external_id' => $docId,
            'source_url' => "https://docs.google.com/document/d/{$docId}/edit",
            'is_active' => true,
            'last_synced_at' => now(),
            'sync_metadata' => $metadata,
        ]);

        $this->generateEmbedding($item);
        return 'added';
    }

    protected function extractContent(\Google\Service\Docs\Document $document): string
    {
        $content = '';
        $body = $document->getBody();
        
        if (!$body) {
            return '';
        }

        foreach ($body->getContent() as $element) {
            $content .= $this->parseStructuralElement($element);
        }

        return trim($content);
    }

    protected function parseStructuralElement($element): string
    {
        $text = '';

        if ($paragraph = $element->getParagraph()) {
            $paragraphText = '';
            
            foreach ($paragraph->getElements() as $elem) {
                if ($textRun = $elem->getTextRun()) {
                    $paragraphText .= $textRun->getContent();
                }
            }

            $style = $paragraph->getParagraphStyle();
            if ($style && $namedStyleType = $style->getNamedStyleType()) {
                switch ($namedStyleType) {
                    case 'HEADING_1':
                        $text .= "# " . trim($paragraphText) . "\n\n";
                        break;
                    case 'HEADING_2':
                        $text .= "## " . trim($paragraphText) . "\n\n";
                        break;
                    case 'HEADING_3':
                        $text .= "### " . trim($paragraphText) . "\n\n";
                        break;
                    default:
                        $text .= $paragraphText;
                }
            } else {
                $text .= $paragraphText;
            }
        }

        if ($table = $element->getTable()) {
            $text .= $this->parseTable($table);
        }

        return $text;
    }

    protected function parseTable($table): string
    {
        $markdown = "\n";
        $rows = $table->getTableRows();
        
        foreach ($rows as $rowIndex => $row) {
            $cells = [];
            foreach ($row->getTableCells() as $cell) {
                $cellText = '';
                foreach ($cell->getContent() as $content) {
                    $cellText .= $this->parseStructuralElement($content);
                }
                $cells[] = trim(str_replace("\n", " ", $cellText));
            }
            
            $markdown .= '| ' . implode(' | ', $cells) . " |\n";
            
            if ($rowIndex === 0 && isset($table->hasColumnHeader) && $table->hasColumnHeader) {
                $markdown .= '| ' . str_repeat('--- | ', count($cells)) . "\n";
            }
        }
        
        return $markdown . "\n";
    }

    protected function extractDocumentId(string $url): ?string
    {
        // Формат: https://docs.google.com/document/d/DOCUMENT_ID/edit
        if (preg_match('/\/document\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }

    protected function saveVersion(KnowledgeItem $item, ?int $userId): void
    {
        $item->versions()->create([
            'version' => $item->version,
            'title' => $item->title,
            'content' => $item->content,
            'embedding' => $item->embedding,
            'metadata' => $item->sync_metadata,
            'created_by' => $userId,
            'change_notes' => 'Автоматическая синхронизация из Google Docs',
        ]);
    }

    protected function generateEmbedding(KnowledgeItem $item): void
    {
        dispatch(function () use ($item) {
            $embeddingService = app(\App\Services\EmbeddingService::class);
            $text = $item->title . "\n\n" . $item->content;
            $embedding = $embeddingService->generateEmbedding($text);
            
            if ($embedding) {
                $item->update(['embedding' => json_encode($embedding)]);
            }
        })->afterResponse();
    }
}