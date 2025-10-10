<template>
  <div class="function-builder">
    <form @submit.prevent="submitFunction">
      <!-- Основная информация -->
      <div class="card mb-4">
        <div class="card-header">
          <h3>Основная информация</h3>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Код функции (английский)</label>
                <input 
                  v-model="functionData.name" 
                  type="text" 
                  class="form-control" 
                  pattern="[a-z_]+"
                  required
                  placeholder="check_order_status"
                >
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Название для отображения</label>
                <input 
                  v-model="functionData.display_name" 
                  type="text" 
                  class="form-control"
                  required
                  placeholder="Проверка статуса заказа"
                >
              </div>
            </div>
          </div>
          <div class="form-group">
            <label>Описание</label>
            <textarea 
              v-model="functionData.description" 
              class="form-control" 
              rows="3"
              placeholder="Функция проверяет статус заказа в базе данных и возвращает информацию клиенту"
            ></textarea>
          </div>
        </div>
      </div>

      <!-- Параметры -->
      <div class="card mb-4">
        <div class="card-header">
          <h3>Параметры функции</h3>
        </div>
        <div class="card-body">
          <parameter-builder 
            v-model="functionData.parameters"
            @update="updateParameters"
          />
        </div>
      </div>

      <!-- Триггеры -->
      <div class="card mb-4">
        <div class="card-header">
          <h3>Триггеры</h3>
        </div>
        <div class="card-body">
          <trigger-builder 
            v-model="functionData.triggers"
            :available-intents="availableIntents"
            @update="updateTriggers"
          />
        </div>
      </div>

      <!-- Действия -->
      <div class="card mb-4">
        <div class="card-header">
          <h3>Действия</h3>
        </div>
        <div class="card-body">
          <action-builder 
            v-model="functionData.actions"
            :parameters="functionData.parameters"
            :crm-integrations="crmIntegrations"
            @update="updateActions"
          />
        </div>
      </div>

      <!-- Поведение -->
      <div class="card mb-4">
        <div class="card-header">
          <h3>Поведение после выполнения</h3>
        </div>
        <div class="card-body">
          <behavior-builder 
            v-model="functionData.behavior"
            @update="updateBehavior"
          />
        </div>
      </div>

      <!-- Кнопки -->
      <div class="form-actions">
        <button type="submit" class="btn btn-primary btn-lg">
          Создать функцию
        </button>
        <button type="button" class="btn btn-secondary btn-lg" @click="testFunction">
          Тестировать
        </button>
        <button type="button" class="btn btn-outline-secondary btn-lg" @click="saveAsDraft">
          Сохранить черновик
        </button>
      </div>
    </form>

    <!-- Модальное окно тестирования -->
    <test-modal 
      v-if="showTestModal"
      :function-data="functionData"
      @close="showTestModal = false"
    />
  </div>
</template>

<script>
import ParameterBuilder from './ParameterBuilder.vue';
import TriggerBuilder from './TriggerBuilder.vue';
import ActionBuilder from './ActionBuilder.vue';
import BehaviorBuilder from './BehaviorBuilder.vue';
import TestModal from './TestModal.vue';

export default {
  name: 'FunctionBuilder',
  components: {
    ParameterBuilder,
    TriggerBuilder,
    ActionBuilder,
    BehaviorBuilder,
    TestModal
  },
  data() {
    return {
      functionData: {
        name: '',
        display_name: '',
        description: '',
        parameters: [],
        triggers: [],
        actions: [],
        behavior: {
          on_success: 'continue',
          on_error: 'continue',
          success_message: '',
          error_message: ''
        }
      },
      crmIntegrations: [],
      availableIntents: [],
      showTestModal: false,
      botId: null,
      organizationSlug: null,
      submitUrl: '',
      csrfToken: ''
    };
  },
  mounted() {
    // Получаем данные из data-атрибутов
    const element = this.$el.parentElement;
    this.botId = element.dataset.botId;
    this.organizationSlug = element.dataset.organization;
    this.crmIntegrations = JSON.parse(element.dataset.crmIntegrations || '[]');
    this.submitUrl = element.dataset.submitUrl;
    this.csrfToken = element.dataset.csrf;
    
    // Загружаем доступные намерения
    this.loadAvailableIntents();
    
    // Загружаем черновик если есть
    this.loadDraft();
  },
  methods: {
    async loadAvailableIntents() {
      try {
        const response = await fetch(`/api/bots/${this.botId}/intents`);
        const data = await response.json();
        this.availableIntents = data.intents;
      } catch (error) {
        console.error('Failed to load intents:', error);
      }
    },
    
    loadDraft() {
      const draft = localStorage.getItem(`function_draft_${this.botId}`);
      if (draft) {
        try {
          this.functionData = JSON.parse(draft);
        } catch (e) {
          console.error('Failed to load draft:', e);
        }
      }
    },
    
    saveAsDraft() {
      localStorage.setItem(
        `function_draft_${this.botId}`, 
        JSON.stringify(this.functionData)
      );
      this.$toast.success('Черновик сохранен');
    },
    
    updateParameters(parameters) {
      this.functionData.parameters = parameters;
    },
    
    updateTriggers(triggers) {
      this.functionData.triggers = triggers;
    },
    
    updateActions(actions) {
      this.functionData.actions = actions;
    },
    
    updateBehavior(behavior) {
      this.functionData.behavior = behavior;
    },
    
    async submitFunction() {
      try {
        // Валидация
        if (!this.validateFunction()) {
          return;
        }
        
        // Подготовка данных для отправки
        const formData = this.prepareFormData();
        
        // Отправка
        const response = await fetch(this.submitUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': this.csrfToken,
            'Accept': 'application/json'
          },
          body: JSON.stringify(formData)
        });
        
        if (response.ok) {
          // Очищаем черновик
          localStorage.removeItem(`function_draft_${this.botId}`);
          
          // Редирект на страницу функции
          const result = await response.json();
          window.location.href = result.redirect_url;
        } else {
          const error = await response.json();
          this.$toast.error(error.message || 'Ошибка при создании функции');
        }
      } catch (error) {
        console.error('Submit error:', error);
        this.$toast.error('Произошла ошибка при отправке');
      }
    },
    
    validateFunction() {
      // Проверка основных полей
      if (!this.functionData.name || !this.functionData.display_name) {
        this.$toast.error('Заполните обязательные поля');
        return false;
      }
      
      // Проверка действий
      if (this.functionData.actions.length === 0) {
        this.$toast.error('Добавьте хотя бы одно действие');
        return false;
      }
      
      // Проверка триггеров
      if (this.functionData.triggers.length === 0) {
        this.$toast.error('Добавьте хотя бы один триггер');
        return false;
      }
      
      return true;
    },
    
    prepareFormData() {
      // Преобразуем данные в формат для Laravel
      return {
        name: this.functionData.name,
        display_name: this.functionData.display_name,
        description: this.functionData.description,
        parameters: this.functionData.parameters,
        triggers: this.functionData.triggers,
        actions: this.functionData.actions,
        behavior: this.functionData.behavior
      };
    },
    
    testFunction() {
      if (!this.validateFunction()) {
        return;
      }
      this.showTestModal = true;
    }
  }
};
</script>

<style scoped>
.function-builder {
  padding: 20px;
}

.card {
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  margin-bottom: 20px;
}

.card-header {
  padding: 15px 20px;
  background: #f8f9fa;
  border-bottom: 1px solid #dee2e6;
  border-radius: 8px 8px 0 0;
}

.card-header h3 {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
}

.card-body {
  padding: 20px;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 5px;
  font-weight: 500;
  color: #495057;
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
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-actions {
  display: flex;
  gap: 10px;
  justify-content: center;
  padding: 20px;
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.btn {
  padding: 10px 24px;
  border: none;
  border-radius: 6px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-primary {
  background: #6366f1;
  color: white;
}

.btn-primary:hover {
  background: #5558e3;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.btn-secondary {
  background: #6c757d;
  color: white;
}

.btn-outline-secondary {
  background: white;
  color: #6c757d;
  border: 1px solid #6c757d;
}
</style>