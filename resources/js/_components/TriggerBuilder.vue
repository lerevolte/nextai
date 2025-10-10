<template>
  <div class="trigger-builder">
    <h3>Конструктор триггеров</h3>
    
    <!-- Список триггеров -->
    <div class="triggers-list">
      <div v-for="(trigger, index) in triggers" :key="index" 
           class="trigger-card">
        <div class="trigger-header">
          <select v-model="trigger.type" @change="onTriggerTypeChange(index)">
            <option value="intent">🎯 Намерение (AI)</option>
            <option value="keyword">🔑 Ключевые слова</option>
            <option value="pattern">📝 Паттерн (RegEx)</option>
            <option value="entity">🏷️ Обнаружение сущности</option>
            <option value="sentiment">😊 Тональность</option>
            <option value="schedule">⏰ Расписание</option>
            <option value="webhook">🔗 Webhook</option>
            <option value="condition">🔀 Условие</option>
          </select>
          
          <button @click="removeTrigger(index)" class="btn-remove">
            ❌ Удалить
          </button>
        </div>
        
        <!-- Настройки триггера -->
        <div class="trigger-config">
          <!-- Намерение -->
          <div v-if="trigger.type === 'intent'">
            <label>Выберите намерение:</label>
            <select v-model="trigger.conditions.intent">
              <option value="">-- Выберите --</option>
              <option value="order_status">📦 Статус заказа</option>
              <option value="booking">📅 Запись/Бронирование</option>
              <option value="complaint">😠 Жалоба</option>
              <option value="refund">💸 Возврат</option>
              <option value="faq">❓ Частый вопрос</option>
              <option value="pricing">💰 Цены</option>
              <option value="contact">☎️ Контакты</option>
            </select>
            
            <label>Минимальная уверенность:</label>
            <input type="range" v-model="trigger.conditions.min_confidence" 
                   min="0.5" max="1" step="0.1">
            <span>{{ trigger.conditions.min_confidence }}</span>
            
            <div class="training-phrases">
              <label>Примеры фраз для обучения:</label>
              <div v-for="(phrase, i) in trigger.conditions.training_phrases" 
                   :key="i" class="phrase-input">
                <input v-model="trigger.conditions.training_phrases[i]" 
                       placeholder="Введите пример фразы">
                <button @click="removePhrase(index, i)">✖</button>
              </div>
              <button @click="addPhrase(index)" class="btn-add-phrase">
                + Добавить фразу
              </button>
            </div>
          </div>
          
          <!-- Ключевые слова -->
          <div v-if="trigger.type === 'keyword'">
            <label>Режим поиска:</label>
            <select v-model="trigger.conditions.mode">
              <option value="any">Любое из слов</option>
              <option value="all">Все слова</option>
              <option value="exact">Точное совпадение</option>
            </select>
            
            <label>Ключевые слова:</label>
            <tag-input v-model="trigger.conditions.keywords"
                      placeholder="Введите слово и нажмите Enter">
            </tag-input>
            
            <div class="keyword-suggestions">
              <strong>Рекомендуемые:</strong>
              <span v-for="word in suggestedKeywords" :key="word"
                    @click="addKeyword(index, word)" class="suggestion">
                {{ word }}
              </span>
            </div>
          </div>
          
          <!-- Паттерн -->
          <div v-if="trigger.type === 'pattern'">
            <label>Регулярное выражение:</label>
            <input v-model="trigger.conditions.pattern" 
                   placeholder="/заказ\s*№?\s*(\d+)/i">
            
            <div class="pattern-presets">
              <strong>Готовые паттерны:</strong>
              <button @click="setPattern(index, 'phone')">📱 Телефон</button>
              <button @click="setPattern(index, 'email')">📧 Email</button>
              <button @click="setPattern(index, 'order')">📦 Номер заказа</button>
              <button @click="setPattern(index, 'date')">📅 Дата</button>
              <button @click="setPattern(index, 'time')">⏰ Время</button>
            </div>
            
            <div class="pattern-test">
              <label>Проверить паттерн:</label>
              <input v-model="patternTestText" placeholder="Введите текст для проверки">
              <button @click="testPattern(index)">Тест</button>
              <span v-if="patternTestResult" :class="patternTestResult.match ? 'success' : 'error'">
                {{ patternTestResult.message }}
              </span>
            </div>
          </div>
          
          <!-- Обнаружение сущности -->
          <div v-if="trigger.type === 'entity'">
            <label>Тип сущности:</label>
            <select v-model="trigger.conditions.entity_type">
              <option value="phone">📱 Телефон</option>
              <option value="email">📧 Email</option>
              <option value="date">📅 Дата</option>
              <option value="time">⏰ Время</option>
              <option value="number">🔢 Число</option>
              <option value="money">💰 Сумма денег</option>
              <option value="location">📍 Местоположение</option>
              <option value="person">👤 Имя человека</option>
              <option value="organization">🏢 Организация</option>
            </select>
            
            <label>
              <input type="checkbox" v-model="trigger.conditions.required">
              Обязательная сущность
            </label>
          </div>
          
          <!-- Тональность -->
          <div v-if="trigger.type === 'sentiment'">
            <label>Тональность:</label>
            <select v-model="trigger.conditions.sentiment">
              <option value="positive">😊 Позитивная</option>
              <option value="negative">😠 Негативная</option>
              <option value="neutral">😐 Нейтральная</option>
            </select>
            
            <label>Порог уверенности:</label>
            <input type="range" v-model="trigger.conditions.threshold" 
                   min="-1" max="1" step="0.1">
            <span>{{ trigger.conditions.threshold }}</span>
          </div>
          
          <!-- Условие -->
          <div v-if="trigger.type === 'condition'">
            <div class="condition-builder">
              <div v-for="(cond, i) in trigger.conditions.rules" :key="i" 
                   class="condition-rule">
                <select v-model="cond.field">
                  <option value="message">Сообщение</option>
                  <option value="user_name">Имя пользователя</option>
                  <option value="user_email">Email пользователя</option>
                  <option value="user_phone">Телефон пользователя</option>
                  <option value="conversation_messages_count">Кол-во сообщений</option>
                  <option value="time">Текущее время</option>
                  <option value="day_of_week">День недели</option>
                  <option value="context_var">Переменная контекста</option>
                </select>
                
                <select v-model="cond.operator">
                  <option value="equals">равно</option>
                  <option value="not_equals">не равно</option>
                  <option value="contains">содержит</option>
                  <option value="not_contains">не содержит</option>
                  <option value="starts_with">начинается с</option>
                  <option value="ends_with">заканчивается на</option>
                  <option value="greater">больше</option>
                  <option value="less">меньше</option>
                  <option value="in">в списке</option>
                  <option value="not_in">не в списке</option>
                  <option value="matches">соответствует</option>
                </select>
                
                <input v-model="cond.value" placeholder="Значение">
                
                <select v-if="i < trigger.conditions.rules.length - 1" 
                        v-model="cond.logic">
                  <option value="AND">И</option>
                  <option value="OR">ИЛИ</option>
                </select>
                
                <button @click="removeCondition(index, i)">✖</button>
              </div>
              
              <button @click="addCondition(index)" class="btn-add-condition">
                + Добавить условие
              </button>
            </div>
          </div>
        </div>
        
        <!-- Приоритет -->
        <div class="trigger-priority">
          <label>Приоритет (больше = выше):</label>
          <input type="number" v-model="trigger.priority" min="0" max="100">
        </div>
      </div>
    </div>
    
    <!-- Кнопка добавления триггера -->
    <button @click="addTrigger" class="btn-add-trigger">
      ➕ Добавить триггер
    </button>
    
    <!-- Логика объединения триггеров -->
    <div class="trigger-logic">
      <label>Логика срабатывания:</label>
      <select v-model="triggerLogic">
        <option value="any">Любой триггер (OR)</option>
        <option value="all">Все триггеры (AND)</option>
        <option value="priority">По приоритету (первый подходящий)</option>
      </select>
    </div>
  </div>
</template>

<script>
import TagInput from './TagInput.vue';

export default {
  name: 'TriggerBuilder',
  components: { TagInput },
  props: {
    value: {
      type: Array,
      default: () => []
    }
  },
  data() {
    return {
      triggers: this.value || [],
      triggerLogic: 'any',
      patternTestText: '',
      patternTestResult: null,
      suggestedKeywords: [
        'заказ', 'статус', 'доставка', 'оплата', 'возврат',
        'бронирование', 'запись', 'отмена', 'перенос',
        'цена', 'стоимость', 'скидка', 'акция'
      ],
      patternPresets: {
        phone: '/\\+?[78]?\\s?\\(?\\d{3}\\)?\\s?\\d{3}[\\s-]?\\d{2}[\\s-]?\\d{2}/i',
        email: '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}/i',
        order: '/(?:заказ|order)\\s*№?\\s*(\\d+)/i',
        date: '/\\d{1,2}[\\.\\/\\-]\\d{1,2}[\\.\\/\\-]\\d{2,4}/i',
        time: '/\\d{1,2}:\\d{2}(?::\\d{2})?/i'
      }
    };
  },
  watch: {
    triggers: {
      deep: true,
      handler(val) {
        this.$emit('input', val);
      }
    }
  },
  methods: {
    addTrigger() {
      this.triggers.push({
        type: 'keyword',
        conditions: {
          keywords: [],
          mode: 'any'
        },
        priority: 50
      });
    },
    
    removeTrigger(index) {
      this.triggers.splice(index, 1);
    },
    
    onTriggerTypeChange(index) {
      const trigger = this.triggers[index];
      
      // Сбрасываем условия при смене типа
      switch (trigger.type) {
        case 'intent':
          trigger.conditions = {
            intent: '',
            min_confidence: 0.7,
            training_phrases: []
          };
          break;
        case 'keyword':
          trigger.conditions = {
            keywords: [],
            mode: 'any'
          };
          break;
        case 'pattern':
          trigger.conditions = {
            pattern: ''
          };
          break;
        case 'entity':
          trigger.conditions = {
            entity_type: 'phone',
            required: false
          };
          break;
        case 'sentiment':
          trigger.conditions = {
            sentiment: 'negative',
            threshold: -0.5
          };
          break;
        case 'condition':
          trigger.conditions = {
            rules: [{
              field: 'message',
              operator: 'contains',
              value: '',
              logic: 'AND'
            }]
          };
          break;
      }
    },
    
    addPhrase(triggerIndex) {
      if (!this.triggers[triggerIndex].conditions.training_phrases) {
        this.triggers[triggerIndex].conditions.training_phrases = [];
      }
      this.triggers[triggerIndex].conditions.training_phrases.push('');
    },
    
    removePhrase(triggerIndex, phraseIndex) {
      this.triggers[triggerIndex].conditions.training_phrases.splice(phraseIndex, 1);
    },
    
    addKeyword(triggerIndex, word) {
      if (!this.triggers[triggerIndex].conditions.keywords.includes(word)) {
        this.triggers[triggerIndex].conditions.keywords.push(word);
      }
    },
    
    setPattern(triggerIndex, presetName) {
      this.triggers[triggerIndex].conditions.pattern = this.patternPresets[presetName];
    },
    
    testPattern(triggerIndex) {
      const pattern = this.triggers[triggerIndex].conditions.pattern;
      if (!pattern || !this.patternTestText) return;
      
      try {
        const regex = new RegExp(pattern.replace(/^\/|\/[gimsu]*$/g, ''), 'i');
        const match = regex.test(this.patternTestText);
        const groups = this.patternTestText.match(regex);
        
        this.patternTestResult = {
          match,
          message: match 
            ? `✅ Совпадение найдено${groups && groups[1] ? ': ' + groups.slice(1).join(', ') : ''}` 
            : '❌ Совпадений не найдено'
        };
      } catch (e) {
        this.patternTestResult = {
          match: false,
          message: '❌ Ошибка в регулярном выражении'
        };
      }
    },
    
    addCondition(triggerIndex) {
      this.triggers[triggerIndex].conditions.rules.push({
        field: 'message',
        operator: 'contains',
        value: '',
        logic: 'AND'
      });
    },
    
    removeCondition(triggerIndex, conditionIndex) {
      this.triggers[triggerIndex].conditions.rules.splice(conditionIndex, 1);
    }
  }
};
</script>

<style scoped>
.trigger-builder {
  padding: 20px;
  background: #f8f9fa;
  border-radius: 8px;
}

.triggers-list {
  margin-bottom: 20px;
}

.trigger-card {
  background: white;
  border: 1px solid #dee2e6;
  border-radius: 6px;
  padding: 15px;
  margin-bottom: 15px;
}

.trigger-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
}

.trigger-config {
  padding: 15px;
  background: #f8f9fa;
  border-radius: 4px;
}

.trigger-config label {
  display: block;
  margin-top: 10px;
  margin-bottom: 5px;
  font-weight: 500;
  color: #495057;
}

.trigger-config input,
.trigger-config select {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid #ced4da;
  border-radius: 4px;
  font-size: 14px;
}

.training-phrases,
.condition-builder {
  margin-top: 15px;
  padding: 10px;
  background: white;
  border-radius: 4px;
}

.phrase-input,
.condition-rule {
  display: flex;
  gap: 10px;
  margin-bottom: 10px;
  align-items: center;
}

.phrase-input input {
  flex: 1;
}

.keyword-suggestions {
  margin-top: 10px;
  padding: 10px;
  background: #e7f3ff;
  border-radius: 4px;
}

.suggestion {
  display: inline-block;
  margin: 2px;
  padding: 4px 8px;
  background: white;
  border: 1px solid #007bff;
  border-radius: 4px;
  color: #007bff;
  cursor: pointer;
  font-size: 12px;
}

.suggestion:hover {
  background: #007bff;
  color: white;
}

.pattern-presets {
  margin-top: 10px;
}

.pattern-presets button {
  margin: 2px;
  padding: 4px 8px;
  background: #6c757d;
  color: white;
  border: none;
  border-radius: 4px;
  font-size: 12px;
  cursor: pointer;
}

.pattern-presets button:hover {
  background: #5a6268;
}

.pattern-test {
  margin-top: 15px;
  padding: 10px;
  background: white;
  border-radius: 4px;
}

.pattern-test input {
  width: calc(100% - 80px);
  margin-right: 10px;
}

.pattern-test button {
  width: 70px;
}

.success {
  color: #28a745;
  font-weight: 500;
}

.error {
  color: #dc3545;
  font-weight: 500;
}

.btn-remove {
  padding: 4px 12px;
  background: #dc3545;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

.btn-add-trigger {
  width: 100%;
  padding: 12px;
  background: #28a745;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 16px;
  font-weight: 500;
  cursor: pointer;
}

.btn-add-trigger:hover {
  background: #218838;
}

.trigger-priority {
  margin-top: 15px;
  padding-top: 15px;
  border-top: 1px solid #dee2e6;
}

.trigger-priority input {
  width: 100px;
}

.trigger-logic {
  margin-top: 20px;
  padding: 15px;
  background: white;
  border-radius: 6px;
  border: 2px solid #007bff;
}
</style>