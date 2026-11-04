import { reactive } from 'vue';
export const page = reactive({ props: { eventTheme: 'halloween', auth: { user: { id: 'season-test' } } } });
export const usePage = () => page;
