<template>
  <div class="tag-input">
    <div class="tag-list">
      <span v-for="(tag, index) in tags" :key="index" class="tag">
        {{ tag }}
        <button type="button" @click="removeTag(index)" class="tag-remove">×</button>
      </span>
      <input 
        v-model="inputValue"
        @keydown.enter.prevent="addTag"
        @keydown.backspace="handleBackspace"
        :placeholder="tags.length === 0 ? placeholder : ''"
        class="tag-input-field"
        type="text">
    </div>
  </div>
</template>

<script>
export default {
  name: 'TagInput',
  props: {
    value: {
      type: Array,
      default: () => []
    },
    placeholder: {
      type: String,
      default: 'Введите значение и нажмите Enter'
    }
  },
  data() {
    return {
      tags: this.value || [],
      inputValue: ''
    };
  },
  watch: {
    tags(val) {
      this.$emit('input', val);
      this.$emit('update', val);
    }
  },
  methods: {
    addTag() {
      if (this.inputValue.trim() && !this.tags.includes(this.inputValue.trim())) {
        this.tags.push(this.inputValue.trim());
        this.inputValue = '';
      }
    },
    removeTag(index) {
      this.tags.splice(index, 1);
    },
    handleBackspace() {
      if (this.inputValue === '' && this.tags.length > 0) {
        this.tags.pop();
      }
    }
  }
};
</script>

<style scoped>
.tag-input {
  width: 100%;
}

.tag-list {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
  padding: 8px;
  border: 1px solid #ced4da;
  border-radius: 4px;
  min-height: 40px;
  align-items: center;
}

.tag {
  display: inline-flex;
  align-items: center;
  padding: 4px 8px;
  background: #667eea;
  color: white;
  border-radius: 4px;
  font-size: 14px;
}

.tag-remove {
  margin-left: 5px;
  background: none;
  border: none;
  color: white;
  cursor: pointer;
  font-size: 18px;
  line-height: 1;
  padding: 0;
}

.tag-input-field {
  flex: 1;
  border: none;
  outline: none;
  min-width: 120px;
  font-size: 14px;
}
</style>