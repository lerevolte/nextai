<?php

namespace App\Observers;

use App\Models\KnowledgeItem;
use App\Models\KnowledgeItemVersion;

class KnowledgeItemObserver
{
    /**
     * Handle the KnowledgeItem "updating" event.
     * Сохраняем текущую версию перед обновлением
     */
    public function updating(KnowledgeItem $item)
    {
        $original = $item->getOriginal();
        
        if ($item->isDirty('content') || $item->isDirty('title')) {
            // Используем заметки, если они переданы
            $changeNotes = property_exists($item, 'temp_change_notes') && $item->temp_change_notes
                ? $item->temp_change_notes
                : $this->generateChangeNotes($item);
            
            KnowledgeItemVersion::create([
                'knowledge_item_id' => $item->id,
                'version' => $original['version'] ?? 1,
                'title' => $original['title'],
                'content' => $original['content'],
                'embedding' => $original['embedding'],
                'metadata' => $original['metadata'],
                'created_by' => auth()->id(),
                'change_notes' => $changeNotes,
            ]);
            
            $item->version = ($original['version'] ?? 1) + 1;
            
            $item->metadata = array_merge($item->metadata ?? [], [
                'updated_by' => auth()->id(),
                'updated_at' => now()->toIso8601String(),
                'previous_version' => $original['version'] ?? 1,
                'change_notes' => $changeNotes, // Сохраняем и в метаданных
            ]);
        }
    }

    /**
     * Handle the KnowledgeItem "created" event.
     */
    public function created(KnowledgeItem $item)
    {
        // Устанавливаем первую версию
        if (!$item->version) {
            $item->version = 1;
            $item->saveQuietly(); // Сохраняем без вызова событий
        }
    }

    /**
     * Генерация описания изменений
     */
    private function generateChangeNotes(KnowledgeItem $item): string
    {
        $changes = [];
        
        if ($item->isDirty('title')) {
            $changes[] = 'Изменен заголовок';
        }
        
        if ($item->isDirty('content')) {
            $original = $item->getOriginal('content');
            $new = $item->content;
            
            $originalWords = str_word_count($original);
            $newWords = str_word_count($new);
            
            if ($newWords > $originalWords) {
                $changes[] = 'Добавлено ' . ($newWords - $originalWords) . ' слов';
            } elseif ($newWords < $originalWords) {
                $changes[] = 'Удалено ' . ($originalWords - $newWords) . ' слов';
            } else {
                $changes[] = 'Изменен контент';
            }
        }
        
        if ($item->isDirty('source_url')) {
            $changes[] = 'Обновлен источник';
        }
        
        // Если изменения через синхронизацию
        if (request()->route() && str_contains(request()->route()->getName() ?? '', 'sync')) {
            $changes[] = '(автоматическая синхронизация)';
        }
        
        return implode(', ', $changes) ?: 'Обновление контента';
    }
}