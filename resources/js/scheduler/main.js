// resources/js/scheduler/main.js
import { createApp } from 'vue';
import SchedulerApp from './components/SchedulerApp.vue';

const mountEl = document.getElementById('scheduler-app');

if (mountEl) {
    const apiBase = mountEl.dataset.apiBase || '';
    const eventId = mountEl.dataset.event || mountEl.dataset.eventId || '';

    const app = createApp(SchedulerApp, {
        apiBase,
        eventId,
    });

    app.mount(mountEl);
}
