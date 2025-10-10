<template>
  <div class="behavior-builder">
    <div class="row">
      <div class="col-md-6">
        <div class="form-group">
          <label>При успешном выполнении</label>
          <select v-model="behavior.on_success" class="form-control" @change="updateBehavior">
            <option value="continue">Продолжить диалог</option>
            <option value="pause">Поставить на паузу</option>
            <option value="enhance_prompt">Дополнить промпт</option>
          </select>
        </div>
      </div>
      <div class="col-md-6">
        <div class="form-group">
          <label>При ошибке</label>
          <select v-model="behavior.on_error" class="form-control" @change="updateBehavior">
            <option value="continue">Продолжить диалог</option>
            <option value="pause">Поставить на паузу</option>
            <option value="notify">Уведомить администратора</option>
          </select>
        </div>
      </div>
    </div>

    <div class="form-group">
      <label>Сообщение при успехе</label>
      <div class="input-with-variables">
        <input 
          v-model="behavior.success_message" 
          type="text" 
          class="form-control"
          placeholder="✅ Заказ #{order_id} успешно создан"
          @change="updateBehavior"
        >
        <div class="variable-hints">
          <span class="hint">Доступные переменные:</span>
          <code v-for="param in availableParams" :key="param" @click="insertVariable(param, 'success')">
            {{ '{' + param + '}' }}
          </code>
        </div>
      </div>
    </div>

    <div class="form-group">
      <label>Сообщение при ошибке</label>
      <input 
        v-model="behavior.error_message" 
        type="text" 
        class="form-control"
        placeholder="❌ Не удалось выполнить действие: {error}"
        @change="updateBehavior"
      >
    </div>

    <div v-if="behavior.on_success === 'enhance_prompt'" class="form-group">
      <label>Дополнение к промпту</label>
      <textarea 
        v-model="behavior.prompt_enhancement" 
        class="form-control" 
        rows="3"
        placeholder="Теперь помоги клиенту выбрать подходящий тариф"
        @change="updateBehavior"
      ></textarea>
    </div>
  </div>
</template>

<script>
export default {
  name: 'BehaviorBuilder',
  props: {
    value: {
      type: Object,
      default: () => ({
        on_success: 'continue',
        on_error: 'continue',
        success_message: '',
        error_message: '',
        prompt_enhancement: ''
      })
    },
    parameters: {
      type: Array,
      default: () => []
    }
  },
  data() {
    return {
      behavior: this.value
    };
  },
  computed: {
    availableParams() {
      return this.parameters.map(p => p.code);
    }
  },
  methods: {
    updateBehavior() {
      this.$emit('input', this.behavior);
      this.$emit('update', this.behavior);
    },
    insertVariable(param, field) {
      if (field === 'success') {
        this.behavior.success_message += ` {${param}}`;
      } else {
        this.behavior.error_message += ` {${param}}`;
      }
      this.updateBehavior();
    }
  }
};
</script>