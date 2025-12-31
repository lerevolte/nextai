<template>
  <div class="action-builder">
    <div class="actions-list">
      <draggable 
        v-model="actions" 
        handle=".drag-handle"
        @change="updateActions">
        <div v-for="(action, index) in actions" 
             :key="action.id || index" 
             class="action-item">
          <div class="action-header">
            <div class="action-header-left">
              <span class="drag-handle">⋮⋮</span>
              <span class="action-number">{{ index + 1 }}</span>
              <span class="action-title">{{ getActionTitle(action) }}</span>
            </div>
            <div class="action-header-right">
              <button type="button" 
                      @click="action.collapsed = !action.collapsed" 
                      class="btn-collapse">
                {{ action.collapsed ? '▼' : '▲' }}
              </button>
              <button type="button" 
                      @click="duplicateAction(index)" 
                      class="btn-duplicate"
                      title="Дублировать">
                📋
              </button>
              <button type="button" 
                      @click="removeAction(index)" 
                      class="btn-remove">
                ✕
              </button>
            </div>
          </div>
          
          <div v-show="!action.collapsed" class="action-content">
            <!-- Выбор типа действия -->
            <div class="action-type-selector">
              <div class="form-group">
                <label>Категория действия</label>
                <select 
                  v-model="action.category" 
                  class="form-control"
                  @change="onCategoryChange(index)">
                  <option value="">Выберите категорию</option>
                  <option value="crm">🏢 CRM операции</option>
                  <option value="database">🗃️ База данных</option>
                  <option value="communication">💬 Коммуникации</option>
                  <option value="calendar">📅 Календарь</option>
                  <option value="payment">💳 Платежи</option>
                  <option value="analytics">📊 Аналитика</option>
                  <option value="ai">🤖 AI обработка</option>
                  <option value="integration">🔗 Интеграции</option>
                  <option value="flow">🔀 Управление потоком</option>
                </select>
              </div>
              
              <div v-if="action.category" class="form-group">
                <label>Действие</label>
                <select 
                  v-model="action.type" 
                  class="form-control"
                  @change="onActionTypeChange(index)">
                  <option value="">Выберите действие</option>
                  <option v-for="(actionType, key) in getActionTypes(action.category)" 
                          :key="key"
                          :value="key">
                    {{ actionType.icon }} {{ actionType.name }}
                  </option>
                </select>
              </div>
              
              <div v-if="action.type && needsProvider(action)" class="form-group">
                <label>Провайдер</label>
                <select 
                  v-model="action.provider" 
                  class="form-control"
                  @change="onProviderChange(index)">
                  <option value="">Выберите провайдер</option>
                  <option v-for="provider in getProviders(action)" 
                          :key="provider.value"
                          :value="provider.value">
                    {{ provider.name }}
                  </option>
                </select>
              </div>
            </div>
            
            <!-- Конфигурация действия -->
            <div v-if="action.type && action.provider" class="action-configuration">
              <!-- CRM действия -->
              <div v-if="action.category === 'crm'" class="crm-config">
                <crm-action-config
                  :action="action"
                  :index="index"
                  :parameters="parameters"
                  :crm-integrations="crmIntegrations"
                  @update="updateAction(index, $event)"/>
              </div>
              
              <!-- База данных -->
              <div v-else-if="action.category === 'database'" class="database-config">
                <div class="form-group">
                  <label>SQL запрос</label>
                  <textarea 
                    v-model="action.config.query" 
                    class="form-control code-editor"
                    rows="4"
                    placeholder="SELECT * FROM orders WHERE id = :order_id"
                    @blur="updateActions"></textarea>
                  <div class="variable-helper">
                    <span>Доступные параметры:</span>
                    <code v-for="param in parameters" 
                          :key="param.code"
                          @click="insertParam(index, 'query', param.code)">
                      :{{ param.code }}
                    </code>
                  </div>
                </div>
              </div>
              
              <!-- Коммуникации -->
              <div v-else-if="action.category === 'communication'" class="communication-config">
                <communication-action-config
                  :action="action"
                  :index="index"
                  :parameters="parameters"
                  @update="updateAction(index, $event)"/>
              </div>
              
              <!-- Календарь -->
              <div v-else-if="action.category === 'calendar'" class="calendar-config">
                <calendar-action-config
                  :action="action"
                  :index="index"
                  :parameters="parameters"
                  @update="updateAction(index, $event)"/>
              </div>
              
              <!-- AI обработка -->
              <div v-else-if="action.category === 'ai'" class="ai-config">
                <ai-action-config
                  :action="action"
                  :index="index"
                  :parameters="parameters"
                  @update="updateAction(index, $event)"/>
              </div>
              
              <!-- Webhook/API -->
              <div v-else-if="action.type === 'webhook' || action.type === 'api_call'" 
                   class="webhook-config">
                <div class="row">
                  <div class="col-md-8">
                    <div class="form-group">
                      <label>URL</label>
                      <input 
                        v-model="action.config.url" 
                        type="url" 
                        class="form-control"
                        placeholder="https://api.example.com/webhook"
                        @blur="updateActions">
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>Метод</label>
                      <select 
                        v-model="action.config.method" 
                        class="form-control"
                        @change="updateActions">
                        <option value="GET">GET</option>
                        <option value="POST">POST</option>
                        <option value="PUT">PUT</option>
                        <option value="PATCH">PATCH</option>
                        <option value="DELETE">DELETE</option>
                      </select>
                    </div>
                  </div>
                </div>
                
                <div class="form-group">
                  <label>Заголовки (JSON)</label>
                  <textarea 
                    v-model="action.config.headers" 
                    class="form-control code-editor"
                    rows="3"
                    placeholder='{"Content-Type": "application/json", "Authorization": "Bearer {api_token}"}'
                    @blur="updateActions"></textarea>
                </div>
                
                <div class="form-group">
                  <label>Тело запроса (JSON)</label>
                  <textarea 
                    v-model="action.config.body" 
                    class="form-control code-editor"
                    rows="5"
                    placeholder='{"order_id": "{order_id}", "status": "completed"}'
                    @blur="updateActions"></textarea>
                  <div class="variable-helper">
                    <span>Доступные параметры:</span>
                    <code v-for="param in parameters" 
                          :key="param.code"
                          @click="insertParam(index, 'body', param.code)">
                      {{ '{' + param.code + '}' }}
                    </code>
                  </div>
                </div>
              </div>
              
              <!-- Управление потоком -->
              <div v-else-if="action.category === 'flow'" class="flow-config">
                <flow-action-config
                  :action="action"
                  :index="index"
                  :parameters="parameters"
                  :all-actions="actions"
                  @update="updateAction(index, $event)"/>
              </div>
            </div>
            
            <!-- Обработка результата -->
            <div v-if="action.type" class="result-handling">
              <h5>Обработка результата</h5>
              <div class="form-group">
                <label>Сохранить результат в переменную</label>
                <input 
                  v-model="action.result_variable" 
                  type="text" 
                  class="form-control"
                  placeholder="result_1"
                  pattern="[a-z_]+"
                  @blur="updateActions">
                <div class="hint-text">
                  Результат будет доступен в последующих действиях как {result_1}
                </div>
              </div>
              
              <div class="form-group">
                <label>При ошибке</label>
                <select 
                  v-model="action.on_error" 
                  class="form-control"
                  @change="updateActions">
                  <option value="stop">Остановить выполнение</option>
                  <option value="continue">Продолжить выполнение</option>
                  <option value="retry">Повторить попытку</option>
                  <option value="fallback">Выполнить альтернативное действие</option>
                </select>
              </div>
              
              <div v-if="action.on_error === 'retry'" class="form-group">
                <label>Количество попыток</label>
                <input 
                  v-model.number="action.retry_count" 
                  type="number" 
                  class="form-control"
                  min="1" 
                  max="5"
                  @blur="updateActions">
              </div>
            </div>
          </div>
        </div>
      </draggable>
    </div>
    
    <!-- Кнопка добавления действия -->
    <button type="button" @click="addAction" class="btn-add-action">
      <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"/>
      </svg>
      Добавить действие
    </button>
    
    <!-- Шаблоны действий -->
    <div v-if="showTemplates" class="action-templates">
      <h5>Популярные сценарии:</h5>
      <div class="template-list">
        <button v-for="template in actionTemplates" 
                :key="template.id"
                @click="addFromTemplate(template)"
                class="template-card">
          <div class="template-icon">{{ template.icon }}</div>
          <div class="template-info">
            <div class="template-name">{{ template.name }}</div>
            <div class="template-desc">{{ template.description }}</div>
          </div>
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import draggable from 'vuedraggable';
import CrmActionConfig from './actions/CrmActionConfig.vue';
import CommunicationActionConfig from './actions/CommunicationActionConfig.vue';
import CalendarActionConfig from './actions/CalendarActionConfig.vue';
import AiActionConfig from './actions/AiActionConfig.vue';
import FlowActionConfig from './actions/FlowActionConfig.vue';

export default {
  name: 'ActionBuilder',
  components: { 
    draggable,
    CrmActionConfig,
    CommunicationActionConfig,
    CalendarActionConfig,
    AiActionConfig,
    FlowActionConfig
  },
  props: {
    value: {
      type: Array,
      default: () => []
    },
    parameters: {
      type: Array,
      default: () => []
    },
    crmIntegrations: {
      type: Array,
      default: () => []
    }
  },
  data() {
    return {
      actions: this.value.length > 0 ? this.value : [],
      showTemplates: true,
      actionTypes: {
        crm: {
          create_lead: { name: 'Создать лид', icon: '📝' },
          update_lead: { name: 'Обновить лид', icon: '✏️' },
          create_deal: { name: 'Создать сделку', icon: '💰' },
          update_deal: { name: 'Обновить сделку', icon: '🔄' },
          change_stage: { name: 'Изменить стадию', icon: '📊' },
          create_task: { name: 'Создать задачу', icon: '📋' },
          add_comment: { name: 'Добавить комментарий', icon: '💬' },
          get_entity: { name: 'Получить данные', icon: '🔍' }
        },
        database: {
          query: { name: 'SQL запрос', icon: '🗃️' },
          get_order: { name: 'Получить заказ', icon: '📦' },
          update_order: { name: 'Обновить заказ', icon: '📝' },
          check_inventory: { name: 'Проверить наличие', icon: '📊' },
          get_user_data: { name: 'Данные пользователя', icon: '👤' }
        },
        communication: {
          send_email: { name: 'Отправить Email', icon: '📧' },
          send_sms: { name: 'Отправить SMS', icon: '📱' },
          send_telegram: { name: 'Telegram сообщение', icon: '✈️' },
          send_whatsapp: { name: 'WhatsApp сообщение', icon: '💬' },
          schedule_call: { name: 'Запланировать звонок', icon: '☎️' },
          transfer_to_operator: { name: 'Передать оператору', icon: '👤' }
        },
        calendar: {
          create_event: { name: 'Создать событие', icon: '📅' },
          check_availability: { name: 'Проверить доступность', icon: '🕐' },
          book_appointment: { name: 'Записать на прием', icon: '📝' },
          cancel_appointment: { name: 'Отменить запись', icon: '❌' },
          reschedule: { name: 'Перенести встречу', icon: '🔄' }
        },
        payment: {
          create_invoice: { name: 'Создать счет', icon: '🧾' },
          check_payment: { name: 'Проверить оплату', icon: '💳' },
          process_refund: { name: 'Оформить возврат', icon: '💸' },
          send_payment_link: { name: 'Отправить ссылку оплаты', icon: '🔗' }
        },
        analytics: {
          track_event: { name: 'Отследить событие', icon: '📊' },
          update_metrics: { name: 'Обновить метрики', icon: '📈' },
          log_interaction: { name: 'Логировать взаимодействие', icon: '📝' }
        },
        ai: {
          classify_intent: { name: 'Классифицировать намерение', icon: '🤖' },
          sentiment_analysis: { name: 'Анализ тональности', icon: '😊' },
          extract_entities: { name: 'Извлечь сущности', icon: '🔍' },
          generate_response: { name: 'Сгенерировать ответ', icon: '💬' },
          translate: { name: 'Перевести текст', icon: '🌐' }
        },
        integration: {
          webhook: { name: 'Webhook запрос', icon: '🔗' },
          api_call: { name: 'API вызов', icon: '🌐' },
          google_sheets: { name: 'Google Sheets', icon: '📊' },
          notion: { name: 'Notion', icon: '📝' },
          slack: { name: 'Slack уведомление', icon: '💬' },
          trello: { name: 'Trello карточка', icon: '📋' }
        },
        flow: {
          condition: { name: 'Условие If/Else', icon: '🔀' },
          loop: { name: 'Цикл', icon: '🔄' },
          wait: { name: 'Ожидание', icon: '⏱️' },
          parallel: { name: 'Параллельное выполнение', icon: '⚡' },
          call_function: { name: 'Вызвать функцию', icon: '📞' }
        }
      },
      actionTemplates: [
        {
          id: 'crm_lead',
          name: 'Создать лид в CRM',
          description: 'Создает лид с данными клиента',
          icon: '📝',
          actions: [
            {
              category: 'crm',
              type: 'create_lead',
              provider: 'bitrix24',
              config: {},
              field_mapping: []
            }
          ]
        },
        {
          id: 'email_notification',
          name: 'Email уведомление',
          description: 'Отправляет email клиенту',
          icon: '📧',
          actions: [
            {
              category: 'communication',
              type: 'send_email',
              provider: 'smtp',
              config: {
                template: 'default'
              }
            }
          ]
        },
        {
          id: 'check_and_notify',
          name: 'Проверка и уведомление',
          description: 'Проверяет данные и отправляет уведомление',
          icon: '🔍',
          actions: [
            {
              category: 'database',
              type: 'query',
              provider: 'mysql',
              config: {}
            },
            {
              category: 'flow',
              type: 'condition',
              provider: 'system',
              config: {}
            },
            {
              category: 'communication',
              type: 'send_sms',
              provider: 'twilio',
              config: {}
            }
          ]
        }
      ]
    };
  },
  watch: {
    actions: {
      deep: true,
      handler(val) {
        this.$emit('input', val);
        this.$emit('update', val);
      }
    }
  },
  methods: {
    addAction() {
      const newAction = {
        id: 'action_' + Date.now(),
        category: '',
        type: '',
        provider: '',
        config: {},
        field_mapping: [],
        result_variable: '',
        on_error: 'stop',
        retry_count: 3,
        collapsed: false
      };
      
      this.actions.push(newAction);
      this.showTemplates = false;
    },
    
    removeAction(index) {
      if (confirm('Удалить действие?')) {
        this.actions.splice(index, 1);
        if (this.actions.length === 0) {
          this.showTemplates = true;
        }
      }
    },
    
    duplicateAction(index) {
      const action = this.actions[index];
      const duplicate = {
        ...JSON.parse(JSON.stringify(action)),
        id: 'action_' + Date.now()
      };
      this.actions.splice(index + 1, 0, duplicate);
    },
    
    updateAction(index, data) {
      this.actions[index] = { ...this.actions[index], ...data };
      this.updateActions();
    },
    
    updateActions() {
      this.$emit('input', this.actions);
      this.$emit('update', this.actions);
    },
    
    getActionTitle(action) {
      if (!action.type) return 'Настройте действие';
      const category = this.actionTypes[action.category];
      if (category && category[action.type]) {
        return `${category[action.type].icon} ${category[action.type].name}`;
      }
      return action.type;
    },
    
    getActionTypes(category) {
      return this.actionTypes[category] || {};
    },
    
    needsProvider(action) {
      return action.category !== 'flow';
    },
    
    getProviders(action) {
      const providers = {
        crm: [
          { value: 'bitrix24', name: 'Bitrix24' },
          { value: 'amocrm', name: 'amoCRM' },
          { value: 'custom', name: 'Другая CRM' }
        ],
        database: [
          { value: 'mysql', name: 'MySQL' },
          { value: 'postgresql', name: 'PostgreSQL' },
          { value: 'mongodb', name: 'MongoDB' }
        ],
        communication: [
          { value: 'smtp', name: 'Email (SMTP)' },
          { value: 'twilio', name: 'SMS (Twilio)' },
          { value: 'telegram', name: 'Telegram Bot' },
          { value: 'whatsapp', name: 'WhatsApp Business' }
        ],
        calendar: [
          { value: 'google', name: 'Google Calendar' },
          { value: 'outlook', name: 'Outlook Calendar' },
          { value: 'caldav', name: 'CalDAV' }
        ],
        payment: [
          { value: 'stripe', name: 'Stripe' },
          { value: 'paypal', name: 'PayPal' },
          { value: 'yookassa', name: 'ЮKassa' }
        ],
        ai: [
          { value: 'openai', name: 'OpenAI' },
          { value: 'anthropic', name: 'Anthropic' },
          { value: 'custom', name: 'Custom AI' }
        ],
        integration: [
          { value: 'custom', name: 'Custom' }
        ]
      };
      
      return providers[action.category] || [];
    },
    
    onCategoryChange(index) {
      this.actions[index].type = '';
      this.actions[index].provider = '';
      this.actions[index].config = {};
      this.updateActions();
    },
    
    onActionTypeChange(index) {
      this.actions[index].provider = '';
      this.actions[index].config = {};
      this.updateActions();
    },
    
    onProviderChange(index) {
      this.actions[index].config = {};
      this.updateActions();
    },
    
    insertParam(actionIndex, field, paramCode) {
      const action = this.actions[actionIndex];
      if (field === 'query') {
        action.config.query = (action.config.query || '') + ` :${paramCode}`;
      } else if (field === 'body') {
        action.config.body = (action.config.body || '') + ` {${paramCode}}`;
      }
      this.updateActions();
    },
    
    addFromTemplate(template) {
      template.actions.forEach(action => {
        this.actions.push({
          ...action,
          id: 'action_' + Date.now() + '_' + Math.random(),
          collapsed: false
        });
      });
      this.showTemplates = false;
    }
  }
};
</script>

<style scoped>
.action-builder {
  padding: 10px;
}

.actions-list {
  margin-bottom: 20px;
}

.action-item {
  background: white;
  border: 2px solid #e5e7eb;
  border-radius: 10px;
  margin-bottom: 20px;
  transition: all 0.3s;
}

.action-item:hover {
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

.action-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 15px 20px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 8px 8px 0 0;
  color: white;
}

.action-header-left {
  display: flex;
  align-items: center;
  gap: 15px;
}

.drag-handle {
  cursor: move;
  opacity: 0.7;
  font-size: 20px;
}

.drag-handle:hover {
  opacity: 1;
}

.action-number {
  width: 30px;
  height: 30px;
  background: white;
  color: #667eea;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
}

.action-title {
  font-weight: 500;
  font-size: 16px;
}

.action-header-right {
  display: flex;
  gap: 10px;
}

.btn-collapse,
.btn-duplicate,
.btn-remove {
  background: rgba(255, 255, 255, 0.2);
  border: none;
  color: white;
  padding: 5px 10px;
  border-radius: 4px;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-collapse:hover,
.btn-duplicate:hover,
.btn-remove:hover {
  background: rgba(255, 255, 255, 0.3);
}

.action-content {
  padding: 20px;
}

.action-type-selector {
  background: #f8f9fa;
  padding: 20px;
  border-radius: 8px;
  margin-bottom: 20px;
}

.action-configuration {
  padding: 20px;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  margin-bottom: 20px;
}

.result-handling {
  padding: 20px;
  background: #f8f9fa;
  border-radius: 8px;
}

.result-handling h5 {
  margin-bottom: 15px;
  color: #495057;
  font-size: 16px;
  font-weight: 600;
}

.form-group {
  margin-bottom: 15px;
}

.form-group label {
  display: block;
  margin-bottom: 5px;
  font-weight: 500;
  color: #495057;
  font-size: 14px;
}

.form-control {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid #ced4da;
  border-radius: 4px;
  font-size: 14px;
}

.form-control:focus {
  outline: none;
  border-color: #667eea;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.code-editor {
  font-family: 'Monaco', 'Courier New', monospace;
  font-size: 13px;
  background: #f8f9fa;
}

.variable-helper {
  margin-top: 10px;
  padding: 10px;
  background: #e7f3ff;
  border-radius: 4px;
}

.variable-helper span {
  margin-right: 10px;
  font-size: 12px;
  color: #6c757d;
}

.variable-helper code {
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

.variable-helper code:hover {
  background: #007bff;
  color: white;
}

.hint-text {
  font-size: 12px;
  color: #6c757d;
  margin-top: 4px;
}

.btn-add-action {
  width: 100%;
  padding: 15px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border: none;
  border-radius: 10px;
  font-weight: 500;
  font-size: 16px;
  cursor: pointer;
  transition: all 0.3s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
}

.btn-add-action:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
}

.action-templates {
  margin-top: 30px;
  padding: 20px;
  background: #f8f9fa;
  border-radius: 10px;
}

.action-templates h5 {
  margin-bottom: 20px;
  color: #495057;
  font-weight: 600;
}

.template-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 15px;
}

.template-card {
  padding: 15px;
  background: white;
  border: 2px solid #e5e7eb;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 15px;
}

.template-card:hover {
  border-color: #667eea;
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(102, 126, 234, 0.2);
}

.template-icon {
  font-size: 32px;
}

.template-info {
  flex: 1;
}

.template-name {
  font-weight: 600;
  margin-bottom: 4px;
}

.template-desc {
  font-size: 12px;
  color: #6c757d;
}

.row {
  display: flex;
  margin: -10px;
}

.col-md-4,
.col-md-8 {
  padding: 10px;
}

.col-md-4 {
  flex: 0 0 33.333333%;
}

.col-md-8 {
  flex: 0 0 66.666667%;
}
</style>