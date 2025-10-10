<template>
  <div class="parameter-builder">
    <div class="parameters-list">
      <div v-for="(param, index) in parameters" :key="index" 
           class="parameter-item">
        <div class="parameter-header">
          <span class="parameter-number">{{ index + 1 }}</span>
          <button type="button" @click="removeParameter(index)" class="btn-remove">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
              <path d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"/>
            </svg>
          </button>
        </div>
        
        <div class="parameter-content">
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Код параметра <span class="required">*</span></label>
                <input 
                  v-model="param.code" 
                  type="text" 
                  class="form-control"
                  pattern="[a-z_]+"
                  placeholder="order_id"
                  @input="validateCode(index, $event)"
                  @blur="updateParameter(index)"
                  required
                >
                <div v-if="param.codeError" class="error-text">
                  {{ param.codeError }}
                </div>
                <div class="hint-text">
                  Только латинские буквы и подчеркивание
                </div>
              </div>
            </div>
            
            <div class="col-md-6">
              <div class="form-group">
                <label>Название <span class="required">*</span></label>
                <input 
                  v-model="param.name" 
                  type="text" 
                  class="form-control"
                  placeholder="Номер заказа"
                  @blur="updateParameter(index)"
                  required
                >
              </div>
            </div>
          </div>
          
          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                <label>Тип данных</label>
                <select 
                  v-model="param.type" 
                  class="form-control"
                  @change="onTypeChange(index)">
                  <option value="string">🔤 Строка</option>
                  <option value="number">🔢 Число</option>
                  <option value="boolean">✓ Да/Нет</option>
                  <option value="date">📅 Дата</option>
                  <option value="datetime">🕐 Дата и время</option>
                  <option value="email">📧 Email</option>
                  <option value="phone">📱 Телефон</option>
                  <option value="url">🔗 URL</option>
                  <option value="json">{ } JSON</option>
                  <option value="array">[ ] Массив</option>
                </select>
              </div>
            </div>
            
            <div class="col-md-4">
              <div class="form-group">
                <label>Обязательность</label>
                <div class="custom-control custom-switch">
                  <input 
                    type="checkbox" 
                    class="custom-control-input" 
                    :id="`required_${index}`"
                    v-model="param.is_required"
                    @change="updateParameter(index)">
                  <label class="custom-control-label" :for="`required_${index}`">
                    {{ param.is_required ? 'Обязательный' : 'Необязательный' }}
                  </label>
                </div>
              </div>
            </div>
            
            <div class="col-md-4">
              <div class="form-group">
                <label>Значение по умолчанию</label>
                <input 
                  v-if="param.type !== 'boolean'"
                  v-model="param.default_value" 
                  type="text" 
                  class="form-control"
                  :placeholder="getDefaultPlaceholder(param.type)"
                  @blur="updateParameter(index)">
                <select 
                  v-else
                  v-model="param.default_value" 
                  class="form-control"
                  @change="updateParameter(index)">
                  <option value="">Не задано</option>
                  <option value="true">Да</option>
                  <option value="false">Нет</option>
                </select>
              </div>
            </div>
          </div>
          
          <div class="form-group">
            <label>Описание</label>
            <textarea 
              v-model="param.description" 
              class="form-control" 
              rows="2"
              placeholder="Опишите назначение параметра и пример значения"
              @blur="updateParameter(index)"></textarea>
          </div>
          
          <!-- Расширенные настройки -->
          <div class="advanced-settings">
            <button type="button" 
                    @click="param.showAdvanced = !param.showAdvanced"
                    class="btn-link">
              <span v-if="!param.showAdvanced">▶</span>
              <span v-else>▼</span>
              Расширенные настройки
            </button>
            
            <div v-show="param.showAdvanced" class="advanced-content">
              <!-- Правила валидации -->
              <div class="form-group">
                <label>Правила валидации</label>
                <div class="validation-rules">
                  <div v-if="param.type === 'string'" class="rule-group">
                    <label>
                      <input type="checkbox" v-model="param.validation.min_length_enabled">
                      Мин. длина:
                    </label>
                    <input 
                      v-if="param.validation.min_length_enabled"
                      type="number" 
                      v-model="param.validation.min_length"
                      class="form-control form-control-sm"
                      min="0">
                    
                    <label>
                      <input type="checkbox" v-model="param.validation.max_length_enabled">
                      Макс. длина:
                    </label>
                    <input 
                      v-if="param.validation.max_length_enabled"
                      type="number" 
                      v-model="param.validation.max_length"
                      class="form-control form-control-sm"
                      min="1">
                    
                    <label>
                      <input type="checkbox" v-model="param.validation.pattern_enabled">
                      Паттерн (RegEx):
                    </label>
                    <input 
                      v-if="param.validation.pattern_enabled"
                      type="text" 
                      v-model="param.validation.pattern"
                      class="form-control form-control-sm"
                      placeholder="/^[A-Z0-9]+$/">
                  </div>
                  
                  <div v-if="param.type === 'number'" class="rule-group">
                    <label>
                      <input type="checkbox" v-model="param.validation.min_enabled">
                      Минимум:
                    </label>
                    <input 
                      v-if="param.validation.min_enabled"
                      type="number" 
                      v-model="param.validation.min"
                      class="form-control form-control-sm">
                    
                    <label>
                      <input type="checkbox" v-model="param.validation.max_enabled">
                      Максимум:
                    </label>
                    <input 
                      v-if="param.validation.max_enabled"
                      type="number" 
                      v-model="param.validation.max"
                      class="form-control form-control-sm">
                  </div>
                </div>
              </div>
              
              <!-- Подсказки для извлечения -->
              <div class="form-group">
                <label>Подсказки для AI извлечения</label>
                <textarea 
                  v-model="param.extraction_hints" 
                  class="form-control" 
                  rows="2"
                  placeholder="Например: Номер заказа обычно начинается с # или содержит 8 цифр"></textarea>
              </div>
              
              <!-- Примеры значений -->
              <div class="form-group">
                <label>Примеры значений</label>
                <tag-input 
                  v-model="param.examples"
                  placeholder="Введите пример и нажмите Enter"
                  @update="updateParameter(index)"/>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Кнопка добавления параметра -->
    <button type="button" @click="addParameter" class="btn-add-parameter">
      <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
      </svg>
      Добавить параметр
    </button>
    
    <!-- Шаблоны параметров -->
    <div v-if="showTemplates" class="parameter-templates">
      <h5>Быстрые шаблоны:</h5>
      <div class="template-grid">
        <button type="button" 
                v-for="template in parameterTemplates" 
                :key="template.code"
                @click="addFromTemplate(template)"
                class="template-btn">
          <span class="template-icon">{{ template.icon }}</span>
          <span class="template-name">{{ template.name }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import TagInput from './TagInput.vue';

export default {
  name: 'ParameterBuilder',
  components: { TagInput },
  props: {
    value: {
      type: Array,
      default: () => []
    }
  },
  data() {
    return {
      parameters: this.value.length > 0 ? this.value : [],
      showTemplates: true,
      parameterTemplates: [
        { 
          code: 'client_name', 
          name: 'Имя клиента', 
          icon: '👤',
          type: 'string',
          is_required: true,
          description: 'Полное имя клиента'
        },
        { 
          code: 'client_phone', 
          name: 'Телефон', 
          icon: '📱',
          type: 'phone',
          is_required: true,
          description: 'Контактный телефон клиента',
          validation: { pattern: '/^\\+?[78]\\d{10}$/' }
        },
        { 
          code: 'client_email', 
          name: 'Email', 
          icon: '📧',
          type: 'email',
          is_required: false,
          description: 'Email адрес клиента'
        },
        { 
          code: 'order_id', 
          name: 'Номер заказа', 
          icon: '📦',
          type: 'string',
          is_required: true,
          description: 'Уникальный номер заказа'
        },
        { 
          code: 'amount', 
          name: 'Сумма', 
          icon: '💰',
          type: 'number',
          is_required: false,
          description: 'Сумма заказа или платежа',
          validation: { min: 0 }
        },
        { 
          code: 'date', 
          name: 'Дата', 
          icon: '📅',
          type: 'date',
          is_required: false,
          description: 'Дата события'
        },
        { 
          code: 'comment', 
          name: 'Комментарий', 
          icon: '💬',
          type: 'string',
          is_required: false,
          description: 'Дополнительная информация'
        },
        { 
          code: 'product_id', 
          name: 'ID продукта', 
          icon: '🏷️',
          type: 'string',
          is_required: false,
          description: 'Идентификатор товара или услуги'
        }
      ]
    };
  },
  watch: {
    parameters: {
      deep: true,
      handler(val) {
        this.$emit('input', val);
        this.$emit('update', val);
      }
    }
  },
  methods: {
    addParameter() {
      const newParam = {
        code: '',
        name: '',
        type: 'string',
        description: '',
        is_required: false,
        default_value: '',
        validation: {},
        extraction_hints: '',
        examples: [],
        showAdvanced: false,
        codeError: null
      };
      
      this.parameters.push(newParam);
      this.showTemplates = false;
      
      // Фокус на новом параметре
      this.$nextTick(() => {
        const inputs = this.$el.querySelectorAll('.parameter-item:last-child input');
        if (inputs.length > 0) {
          inputs[0].focus();
        }
      });
    },
    
    removeParameter(index) {
      if (confirm('Удалить параметр?')) {
        this.parameters.splice(index, 1);
        if (this.parameters.length === 0) {
          this.showTemplates = true;
        }
      }
    },
    
    updateParameter(index) {
      this.$emit('input', this.parameters);
      this.$emit('update', this.parameters);
    },
    
    validateCode(index, event) {
      const value = event.target.value;
      const param = this.parameters[index];
      
      // Проверка формата
      if (value && !/^[a-z_]+$/.test(value)) {
        param.codeError = 'Только латинские буквы в нижнем регистре и подчеркивание';
      } 
      // Проверка уникальности
      else if (this.parameters.some((p, i) => i !== index && p.code === value)) {
        param.codeError = 'Код параметра должен быть уникальным';
      } 
      else {
        param.codeError = null;
      }
    },
    
    onTypeChange(index) {
      const param = this.parameters[index];
      
      // Сброс валидации при смене типа
      param.validation = {};
      
      // Установка валидации по умолчанию
      switch (param.type) {
        case 'email':
          param.validation.pattern = '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$';
          break;
        case 'phone':
          param.validation.pattern = '^\\+?[0-9]{10,15}$';
          break;
        case 'url':
          param.validation.pattern = '^https?://.*';
          break;
      }
      
      this.updateParameter(index);
    },
    
    getDefaultPlaceholder(type) {
      const placeholders = {
        string: 'Текст по умолчанию',
        number: '0',
        date: '2024-01-01',
        datetime: '2024-01-01 12:00',
        email: 'user@example.com',
        phone: '+79001234567',
        url: 'https://example.com',
        json: '{}',
        array: '[]'
      };
      return placeholders[type] || '';
    },
    
    addFromTemplate(template) {
      const newParam = {
        ...template,
        showAdvanced: false,
        codeError: null,
        validation: template.validation || {},
        examples: template.examples || []
      };
      
      this.parameters.push(newParam);
      this.showTemplates = false;
    }
  }
};
</script>

<style scoped>
.parameter-builder {
  padding: 10px;
}

.parameters-list {
  margin-bottom: 20px;
}

.parameter-item {
  background: #f8f9fa;
  border: 1px solid #dee2e6;
  border-radius: 8px;
  margin-bottom: 15px;
  position: relative;
  transition: all 0.3s;
}

.parameter-item:hover {
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.parameter-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 15px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 8px 8px 0 0;
}

.parameter-number {
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

.btn-remove {
  background: rgba(255, 255, 255, 0.2);
  border: none;
  color: white;
  padding: 5px;
  border-radius: 4px;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-remove:hover {
  background: rgba(255, 255, 255, 0.3);
}

.parameter-content {
  padding: 20px;
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
  transition: border-color 0.2s;
}

.form-control:focus {
  outline: none;
  border-color: #667eea;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.required {
  color: #dc3545;
  font-weight: bold;
}

.error-text {
  color: #dc3545;
  font-size: 12px;
  margin-top: 4px;
}

.hint-text {
  color: #6c757d;
  font-size: 12px;
  margin-top: 4px;
}

.custom-control-label {
  padding-left: 25px;
  cursor: pointer;
}

.advanced-settings {
  margin-top: 20px;
  padding-top: 20px;
  border-top: 1px solid #dee2e6;
}

.btn-link {
  background: none;
  border: none;
  color: #667eea;
  cursor: pointer;
  font-weight: 500;
  padding: 0;
}

.advanced-content {
  margin-top: 15px;
  padding: 15px;
  background: white;
  border-radius: 6px;
}

.validation-rules {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: center;
}

.rule-group {
  display: flex;
  gap: 10px;
  align-items: center;
}

.rule-group label {
  margin: 0;
  display: flex;
  align-items: center;
  gap: 5px;
}

.rule-group input[type="number"],
.rule-group input[type="text"] {
  width: 100px;
}

.btn-add-parameter {
  width: 100%;
  padding: 12px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.btn-add-parameter:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

.parameter-templates {
  margin-top: 20px;
  padding: 20px;
  background: #f8f9fa;
  border-radius: 8px;
}

.parameter-templates h5 {
  margin-bottom: 15px;
  color: #495057;
}

.template-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  gap: 10px;
}

.template-btn {
  padding: 10px;
  background: white;
  border: 1px solid #dee2e6;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 5px;
}

.template-btn:hover {
  background: #667eea;
  color: white;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.template-icon {
  font-size: 24px;
}

.template-name {
  font-size: 12px;
  text-align: center;
}

.row {
  display: flex;
  margin: -10px;
}

.col-md-4,
.col-md-6 {
  padding: 10px;
}

.col-md-4 {
  flex: 0 0 33.333333%;
}

.col-md-6 {
  flex: 0 0 50%;
}
</style>