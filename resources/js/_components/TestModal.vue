<template>
  <div class="test-modal-overlay" @click.self="close">
    <div class="test-modal">
      <div class="test-modal-header">
        <h3>🧪 Тестирование функции</h3>
        <button @click="close" class="close-btn">✕</button>
      </div>
      
      <div class="test-modal-body">
        <!-- Вкладки -->
        <div class="test-tabs">
          <button 
            :class="['tab', { active: activeTab === 'simulate' }]"
            @click="activeTab = 'simulate'">
            Симуляция диалога
          </button>
          <button 
            :class="['tab', { active: activeTab === 'parameters' }]"
            @click="activeTab = 'parameters'">
            Ручной ввод параметров
          </button>
          <button 
            :class="['tab', { active: activeTab === 'history' }]"
            @click="activeTab = 'history'">
            История тестов
          </button>
        </div>
        
        <!-- Содержимое вкладок -->
        <div class="test-content">
          <!-- Симуляция диалога -->
          <div v-show="activeTab === 'simulate'" class="simulate-tab">
            <div class="chat-simulator">
              <div class="chat-messages" ref="chatContainer">
                <div v-for="(msg, index) in chatMessages" 
                     :key="index"
                     :class="['message', msg.role]">
                  <div class="message-avatar">
                    {{ msg.role === 'user' ? '👤' : '🤖' }}
                  </div>
                  <div class="message-content">
                    <div class="message-text">{{ msg.content }}</div>
                    <div v-if="msg.functionTriggered" class="function-triggered">
                      ⚡ Функция сработала
                      <span v-if="msg.parameters" class="params-detected">
                        Параметры: {{ Object.keys(msg.parameters).join(', ') }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
              
              <div class="chat-input">
                <input 
                  v-model="testMessage" 
                  @keydown.enter="sendTestMessage"
                  type="text" 
                  placeholder="Введите сообщение для тестирования..."
                  :disabled="isProcessing">
                <button 
                  @click="sendTestMessage"
                  :disabled="isProcessing || !testMessage.trim()"
                  class="send-btn">
                  {{ isProcessing ? '⏳' : '➤' }}
                </button>
              </div>
            </div>
            
            <!-- Предустановленные сценарии -->
            <div class="test-scenarios">
              <h5>Быстрые сценарии:</h5>
              <div class="scenarios-grid">
                <button 
                  v-for="scenario in testScenarios" 
                  :key="scenario.id"
                  @click="runScenario(scenario)"
                  class="scenario-btn">
                  <span class="scenario-icon">{{ scenario.icon }}</span>
                  <span class="scenario-name">{{ scenario.name }}</span>
                </button>
              </div>
            </div>
          </div>
          
          <!-- Ручной ввод параметров -->
          <div v-show="activeTab === 'parameters'" class="parameters-tab">
            <form @submit.prevent="runWithParameters">
              <div class="parameters-form">
                <div v-for="param in functionData.parameters" 
                     :key="param.code"
                     class="param-input-group">
                  <label>
                    {{ param.name }}
                    <span v-if="param.is_required" class="required">*</span>
                  </label>
                  
                  <input 
                    v-if="param.type === 'string' || param.type === 'email' || param.type === 'phone'"
                    v-model="manualParams[param.code]"
                    :type="param.type === 'email' ? 'email' : 'text'"
                    :placeholder="param.description || `Введите ${param.name.toLowerCase()}`"
                    :required="param.is_required"
                    class="form-control">
                  
                  <input 
                    v-else-if="param.type === 'number'"
                    v-model.number="manualParams[param.code]"
                    type="number"
                    :placeholder="param.description"
                    :required="param.is_required"
                    class="form-control">
                  
                  <input 
                    v-else-if="param.type === 'date'"
                    v-model="manualParams[param.code]"
                    type="date"
                    :required="param.is_required"
                    class="form-control">
                  
                  <select 
                    v-else-if="param.type === 'boolean'"
                    v-model="manualParams[param.code]"
                    :required="param.is_required"
                    class="form-control">
                    <option value="">Не указано</option>
                    <option :value="true">Да</option>
                    <option :value="false">Нет</option>
                  </select>
                  
                  <textarea 
                    v-else-if="param.type === 'json' || param.type === 'array'"
                    v-model="manualParams[param.code]"
                    :placeholder="param.type === 'json' ? '{}' : '[]'"
                    :required="param.is_required"
                    class="form-control"
                    rows="3"></textarea>
                  
                  <div v-if="param.description" class="param-hint">
                    {{ param.description }}
                  </div>
                </div>
              </div>
              
              <button type="submit" class="btn-primary" :disabled="isProcessing">
                {{ isProcessing ? 'Выполняется...' : 'Запустить тест' }}
              </button>
            </form>
          </div>
          
          <!-- История тестов -->
          <div v-show="activeTab === 'history'" class="history-tab">
            <div v-if="testHistory.length === 0" class="no-history">
              <p>История тестов пуста</p>
              <p class="hint">Запустите тест, чтобы увидеть результаты здесь</p>
            </div>
            
            <div v-else class="history-list">
              <div v-for="(test, index) in testHistory" 
                   :key="index"
                   class="history-item">
                <div class="history-header">
                  <span :class="['status', test.status]">
                    {{ test.status === 'success' ? '✓' : '✗' }}
                    {{ test.status === 'success' ? 'Успешно' : 'Ошибка' }}
                  </span>
                  <span class="timestamp">{{ formatTime(test.timestamp) }}</span>
                </div>
                
                <div class="history-details">
                  <div v-if="test.trigger" class="detail-row">
                    <span class="label">Триггер:</span>
                    <span class="value">{{ test.trigger }}</span>
                  </div>
                  
                  <div v-if="test.parameters" class="detail-row">
                    <span class="label">Параметры:</span>
                    <pre class="json-view">{{ JSON.stringify(test.parameters, null, 2) }}</pre>
                  </div>
                  
                  <div v-if="test.actions" class="detail-row">
                    <span class="label">Выполненные действия:</span>
                    <ul class="actions-list">
                      <li v-for="(action, i) in test.actions" :key="i">
                        <span :class="['action-status', action.status]">
                          {{ action.status === 'success' ? '✓' : '✗' }}
                        </span>
                        {{ action.name }}
                        <span v-if="action.duration" class="duration">
                          ({{ action.duration }}ms)
                        </span>
                      </li>
                    </ul>
                  </div>
                  
                  <div v-if="test.error" class="detail-row error-row">
                    <span class="label">Ошибка:</span>
                    <span class="error-message">{{ test.error }}</span>
                  </div>
                  
                  <div v-if="test.result" class="detail-row">
                    <span class="label">Результат:</span>
                    <pre class="json-view">{{ JSON.stringify(test.result, null, 2) }}</pre>
                  </div>
                </div>
              </div>
            </div>
            
            <button 
              v-if="testHistory.length > 0"
              @click="clearHistory" 
              class="btn-secondary">
              Очистить историю
            </button>
          </div>
        </div>
        
        <!-- Результаты теста -->
        <div v-if="currentTestResult" class="test-results">
          <h4>Результаты теста</h4>
          
          <div :class="['result-status', currentTestResult.status]">
            <span class="status-icon">
              {{ currentTestResult.status === 'success' ? '✅' : '❌' }}
            </span>
            <span class="status-text">
              {{ currentTestResult.status === 'success' ? 'Тест пройден успешно' : 'Тест завершился с ошибкой' }}
            </span>
          </div>
          
          <div class="result-details">
            <!-- Извлеченные параметры -->
            <div v-if="currentTestResult.extractedParams" class="result-section">
              <h5>📊 Извлеченные параметры:</h5>
              <table class="params-table">
                <tr v-for="(value, key) in currentTestResult.extractedParams" :key="key">
                  <td class="param-name">{{ key }}</td>
                  <td class="param-value">{{ value }}</td>
                </tr>
              </table>
            </div>
            
            <!-- Выполненные действия -->
            <div v-if="currentTestResult.executedActions" class="result-section">
              <h5>⚡ Выполненные действия:</h5>
              <div class="actions-timeline">
                <div v-for="(action, index) in currentTestResult.executedActions" 
                     :key="index"
                     :class="['action-item', action.status]">
                  <div class="action-icon">
                    {{ action.status === 'success' ? '✓' : '✗' }}
                  </div>
                  <div class="action-info">
                    <div class="action-name">{{ action.name }}</div>
                    <div v-if="action.result" class="action-result">
                      Результат: {{ action.result }}
                    </div>
                    <div v-if="action.error" class="action-error">
                      Ошибка: {{ action.error }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Лог выполнения -->
            <div v-if="currentTestResult.executionLog" class="result-section">
              <h5>📝 Лог выполнения:</h5>
              <div class="execution-log">
                <div v-for="(log, index) in currentTestResult.executionLog" 
                     :key="index"
                     :class="['log-entry', log.level]">
                  <span class="log-time">{{ log.time }}</span>
                  <span class="log-level">{{ log.level }}</span>
                  <span class="log-message">{{ log.message }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="test-modal-footer">
        <button @click="exportResults" class="btn-secondary">
          📥 Экспорт результатов
        </button>
        <button @click="close" class="btn-primary">
          Закрыть
        </button>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'TestModal',
  props: {
    functionData: {
      type: Object,
      required: true
    }
  },
  data() {
    return {
      activeTab: 'simulate',
      isProcessing: false,
      
      // Симуляция чата
      chatMessages: [],
      testMessage: '',
      
      // Ручные параметры
      manualParams: {},
      
      // История тестов
      testHistory: [],
      
      // Текущий результат
      currentTestResult: null,
      
      // Тестовые сценарии
      testScenarios: [
        {
          id: 'order_status',
          name: 'Проверка заказа',
          icon: '📦',
          messages: [
            'Где мой заказ #12345?',
            'Хочу узнать статус доставки'
          ]
        },
        {
          id: 'booking',
          name: 'Запись на прием',
          icon: '📅',
          messages: [
            'Хочу записаться на прием',
            'На завтра в 15:00, меня зовут Иван Иванов, телефон +79001234567'
          ]
        },
        {
          id: 'complaint',
          name: 'Жалоба',
          icon: '😠',
          messages: [
            'Я недоволен качеством обслуживания!',
            'Заказ пришел поврежденный, требую возврат'
          ]
        },
        {
          id: 'price',
          name: 'Узнать цену',
          icon: '💰',
          messages: [
            'Сколько стоит доставка?',
            'В Москву, 5 кг'
          ]
        }
      ]
    };
  },
  mounted() {
    // Инициализируем параметры по умолчанию
    this.initializeManualParams();
    
    // Добавляем приветственное сообщение
    this.chatMessages.push({
      role: 'assistant',
      content: '👋 Привет! Это тестовый режим. Напишите сообщение, чтобы проверить срабатывание функции.'
    });
  },
  methods: {
    async sendTestMessage() {
      if (!this.testMessage.trim() || this.isProcessing) return;
      
      // Добавляем сообщение пользователя
      this.chatMessages.push({
        role: 'user',
        content: this.testMessage
      });
      
      const message = this.testMessage;
      this.testMessage = '';
      this.isProcessing = true;
      
      try {
        // Проверяем триггеры
        const triggerResult = await this.checkTriggers(message);
        
        if (triggerResult.matched) {
          // Триггер сработал
          this.chatMessages.push({
            role: 'assistant',
            content: `✅ Триггер "${triggerResult.trigger}" сработал!`,
            functionTriggered: true,
            parameters: triggerResult.parameters
          });
          
          // Выполняем функцию
          const result = await this.executeFunction(triggerResult.parameters);
          this.handleTestResult(result);
        } else {
          // Триггер не сработал
          this.chatMessages.push({
            role: 'assistant',
            content: '❌ Ни один триггер не сработал на это сообщение.'
          });
        }
      } catch (error) {
        this.chatMessages.push({
          role: 'assistant',
          content: `⚠️ Ошибка: ${error.message}`
        });
      } finally {
        this.isProcessing = false;
        this.scrollToBottom();
      }
    },
    
    async checkTriggers(message) {
      // Отправляем запрос на проверку триггеров
      const response = await fetch('/api/functions/test-triggers', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
          function: this.functionData,
          message: message
        })
      });
      
      return await response.json();
    },
    
    async executeFunction(parameters) {
      // Отправляем запрос на выполнение функции
      const response = await fetch('/api/functions/test-execute', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
          function: this.functionData,
          parameters: parameters
        })
      });
      
      return await response.json();
    },
    
    async runWithParameters() {
      this.isProcessing = true;
      
      try {
        const result = await this.executeFunction(this.manualParams);
        this.handleTestResult(result);
      } catch (error) {
        this.currentTestResult = {
          status: 'error',
          error: error.message
        };
      } finally {
        this.isProcessing = false;
      }
    },
    
    async runScenario(scenario) {
      for (const message of scenario.messages) {
        this.testMessage = message;
        await this.sendTestMessage();
        
        // Небольшая задержка между сообщениями
        await new Promise(resolve => setTimeout(resolve, 1000));
      }
    },
    
    handleTestResult(result) {
      this.currentTestResult = result;
      
      // Добавляем в историю
      this.testHistory.unshift({
        ...result,
        timestamp: new Date()
      });
      
      // Ограничиваем историю 20 записями
      if (this.testHistory.length > 20) {
        this.testHistory.pop();
      }
      
      // Показываем результат в чате
      if (result.status === 'success') {
        this.chatMessages.push({
          role: 'assistant',
          content: result.successMessage || '✅ Функция выполнена успешно!'
        });
      } else {
        this.chatMessages.push({
          role: 'assistant',
          content: `❌ Ошибка выполнения: ${result.error}`
        });
      }
    },
    
    initializeManualParams() {
      this.functionData.parameters.forEach(param => {
        if (param.default_value) {
          this.manualParams[param.code] = param.default_value;
        } else {
          this.manualParams[param.code] = '';
        }
      });
    },
    
    scrollToBottom() {
      this.$nextTick(() => {
        if (this.$refs.chatContainer) {
          this.$refs.chatContainer.scrollTop = this.$refs.chatContainer.scrollHeight;
        }
      });
    },
    
    formatTime(timestamp) {
      const date = new Date(timestamp);
      return date.toLocaleString('ru-RU', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
      });
    },
    
    clearHistory() {
      if (confirm('Очистить всю историю тестов?')) {
        this.testHistory = [];
      }
    },
    
    exportResults() {
      const data = {
        function: this.functionData.name,
        timestamp: new Date().toISOString(),
        currentResult: this.currentTestResult,
        history: this.testHistory
      };
      
      const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `test_results_${this.functionData.name}_${Date.now()}.json`;
      a.click();
      window.URL.revokeObjectURL(url);
    },
    
    close() {
      this.$emit('close');
    }
  }
};
</script>

<style scoped>
.test-modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}

.test-modal {
  background: white;
  border-radius: 12px;
  width: 90%;
  max-width: 1200px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.test-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px;
  border-bottom: 1px solid #e5e7eb;
}

.test-modal-header h3 {
  margin: 0;
  font-size: 20px;
}

.close-btn {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: #6b7280;
}

.test-modal-body {
  flex: 1;
  padding: 20px;
  overflow-y: auto;
}

.test-tabs {
  display: flex;
  gap: 10px;
  margin-bottom: 20px;
  border-bottom: 2px solid #e5e7eb;
}

.tab {
  padding: 10px 20px;
  background: none;
  border: none;
  cursor: pointer;
  font-weight: 500;
  color: #6b7280;
  position: relative;
  transition: color 0.2s;
}

.tab.active {
  color: #667eea;
}

.tab.active::after {
  content: '';
  position: absolute;
  bottom: -2px;
  left: 0;
  right: 0;
  height: 2px;
  background: #667eea;
}

.chat-simulator {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  overflow: hidden;
}

.chat-messages {
  height: 300px;
  overflow-y: auto;
  padding: 20px;
  background: #f9fafb;
}

.message {
  display: flex;
  gap: 10px;
  margin-bottom: 15px;
}

.message.user {
  flex-direction: row-reverse;
}

.message-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: #667eea;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
}

.message.user .message-avatar {
  background: #10b981;
}

.message-content {
  max-width: 70%;
}

.message-text {
  padding: 10px 15px;
  background: white;
  border-radius: 8px;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}

.message.user .message-text {
  background: #667eea;
  color: white;
}

.function-triggered {
  margin-top: 5px;
  padding: 5px 10px;
  background: #fef3c7;
  border-radius: 4px;
  font-size: 12px;
  color: #92400e;
}

.params-detected {
  display: block;
  margin-top: 3px;
  font-family: monospace;
}

.chat-input {
  display: flex;
  padding: 10px;
  border-top: 1px solid #e5e7eb;
  background: white;
}

.chat-input input {
  flex: 1;
  padding: 10px;
  border: 1px solid #e5e7eb;
  border-radius: 4px;
  font-size: 14px;
}

.send-btn {
  margin-left: 10px;
  padding: 10px 20px;
  background: #667eea;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 18px;
}

.send-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.test-scenarios {
  margin-top: 20px;
}

.test-scenarios h5 {
  margin-bottom: 10px;
  color: #495057;
}

.scenarios-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  gap: 10px;
}

.scenario-btn {
  padding: 10px;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 5px;
}

.scenario-btn:hover {
  border-color: #667eea;
  background: #f3f4f6;
}

.scenario-icon {
  font-size: 24px;
}

.scenario-name {
  font-size: 12px;
  text-align: center;
}

.parameters-form {
  display: grid;
  gap: 20px;
}

.param-input-group label {
  display: block;
  margin-bottom: 5px;
  font-weight: 500;
  color: #495057;
}

.param-hint {
  margin-top: 5px;
  font-size: 12px;
  color: #6b7280;
}

.form-control {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid #ced4da;
  border-radius: 4px;
  font-size: 14px;
}

.required {
  color: #ef4444;
}

.btn-primary {
  padding: 10px 20px;
  background: #667eea;
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 500;
}

.btn-secondary {
  padding: 10px 20px;
  background: #6b7280;
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
}

.history-list {
  max-height: 400px;
  overflow-y: auto;
}

.history-item {
  padding: 15px;
  background: #f9fafb;
  border-radius: 8px;
  margin-bottom: 10px;
}

.history-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}

.status {
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 500;
}

.status.success {
  background: #d1fae5;
  color: #065f46;
}

.status.error {
  background: #fee2e2;
  color: #991b1b;
}

.timestamp {
  font-size: 12px;
  color: #6b7280;
}

.history-details {
  font-size: 14px;
}

.detail-row {
  margin-bottom: 10px;
}

.detail-row .label {
  font-weight: 500;
  margin-right: 10px;
}

.json-view {
  margin-top: 5px;
  padding: 10px;
  background: white;
  border-radius: 4px;
  font-size: 12px;
  font-family: monospace;
  overflow-x: auto;
}

.actions-list {
  margin-top: 5px;
  padding-left: 20px;
}

.action-status {
  margin-right: 5px;
}

.action-status.success {
  color: #10b981;
}

.action-status.error {
  color: #ef4444;
}

.duration {
  font-size: 12px;
  color: #6b7280;
}

.error-row .error-message {
  color: #ef4444;
}

.no-history {
  text-align: center;
  padding: 40px;
  color: #6b7280;
}

.no-history .hint {
  margin-top: 10px;
  font-size: 14px;
  color: #9ca3af;
}

.test-results {
  margin-top: 30px;
  padding: 20px;
  background: #f9fafb;
  border-radius: 8px;
}

.test-results h4 {
  margin-bottom: 15px;
}

.result-status {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 15px;
  border-radius: 8px;
  margin-bottom: 20px;
}

.result-status.success {
  background: #d1fae5;
  color: #065f46;
}

.result-status.error {
  background: #fee2e2;
  color: #991b1b;
}

.status-icon {
  font-size: 24px;
}

.result-section {
  margin-bottom: 25px;
}

.result-section h5 {
  margin-bottom: 10px;
  color: #495057;
}
.params-table {
  width: 100%;
  border-collapse: collapse;
}

.params-table td {
  padding: 8px;
  border: 1px solid #e5e7eb;
}

.param-name {
  background: #f3f4f6;
  font-weight: 500;
  width: 30%;
}

.param-value {
  font-family: monospace;
  font-size: 13px;
}

.actions-timeline {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.action-item {
  display: flex;
  gap: 10px;
  padding: 10px;
  background: white;
  border-radius: 6px;
  border-left: 3px solid #e5e7eb;
}

.action-item.success {
  border-left-color: #10b981;
}

.action-item.error {
  border-left-color: #ef4444;
}

.action-icon {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  background: #f3f4f6;
}

.action-item.success .action-icon {
  background: #d1fae5;
  color: #065f46;
}

.action-item.error .action-icon {
  background: #fee2e2;
  color: #991b1b;
}

.action-info {
  flex: 1;
}

.action-name {
  font-weight: 500;
  margin-bottom: 4px;
}

.action-result {
  font-size: 12px;
  color: #6b7280;
}

.action-error {
  font-size: 12px;
  color: #ef4444;
}

.execution-log {
  max-height: 200px;
  overflow-y: auto;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 4px;
  padding: 10px;
  font-family: monospace;
  font-size: 12px;
}

.log-entry {
  display: flex;
  gap: 10px;
  margin-bottom: 5px;
}

.log-time {
  color: #6b7280;
}

.log-level {
  font-weight: bold;
  text-transform: uppercase;
}

.log-entry.info .log-level {
  color: #3b82f6;
}

.log-entry.success .log-level {
  color: #10b981;
}

.log-entry.warning .log-level {
  color: #f59e0b;
}

.log-entry.error .log-level {
  color: #ef4444;
}

.test-modal-footer {
  display: flex;
  justify-content: space-between;
  padding: 20px;
  border-top: 1px solid #e5e7eb;
}
</style>