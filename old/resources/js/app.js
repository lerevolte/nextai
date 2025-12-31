// resources/js/app.js
require('./bootstrap');

import { createApp } from 'vue';

// Импортируем компоненты (если они уже созданы)
const components = {
    'trigger-builder': () => import('./components/TriggerBuilder.vue'),
    'action-builder': () => import('./components/ActionBuilder.vue'),
    'parameter-builder': () => import('./components/ParameterBuilder.vue'),
    'function-builder': () => import('./components/FunctionBuilder.vue'),
    'test-modal': () => import('./components/TestModal.vue'),
};

// Инициализация Vue приложений
document.addEventListener('DOMContentLoaded', () => {
    // Находим все элементы с data-vue-component
    const vueElements = document.querySelectorAll('[data-vue-component]');
    
    vueElements.forEach(element => {
        const componentName = element.dataset.vueComponent;
        const component = components[componentName];
        
        if (component) {
            const app = createApp({
                components: {
                    [componentName]: component
                },
                template: `<${componentName} v-bind="$attrs" />`,
                ...parseProps(element)
            });
            
            app.mount(element);
        }
    });
});

// Функция для парсинга props из data-атрибутов
function parseProps(element) {
    const props = {};
    const attrs = element.attributes;
    
    for (let i = 0; i < attrs.length; i++) {
        const attr = attrs[i];
        if (attr.name.startsWith('data-') && attr.name !== 'data-vue-component') {
            const propName = attr.name.slice(5).replace(/-([a-z])/g, (g) => g[1].toUpperCase());
            try {
                props[propName] = JSON.parse(attr.value);
            } catch {
                props[propName] = attr.value;
            }
        }
    }
    
    return { data: () => props };
}

// Alpine.js для остальной интерактивности
import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.start();