import template from './template.twig';
import './style.scss';

const { Component, Mixin } = Shopware;

Component.register('frosh-tools-tab-shopware-operator', {
    template,

    inject: ['froshToolsService'],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            isLoading: true,
            data: null,
            error: null
        };
    },

    created() {
        this.loadData();
    },

    methods: {
        async loadData() {
            this.isLoading = true;
            this.error = null;
            try {
                this.data = await this.froshToolsService.shopwareOperatorCheck();
            } catch (e) {
                this.error = e.response?.data?.message || e.message;
            } finally {
                this.isLoading = false;
            }
        },

        getStatusClass(status) {
            const lowerStatus = status ? status.toLowerCase() : '';
            if (['running', 'connected', 'healthy', 'scheduled'].includes(lowerStatus)) {
                return 'status-badge status-badge--success';
            }
            if (['pending'].includes(lowerStatus)) {
                return 'status-badge status-badge--warning';
            }
            return 'status-badge status-badge--error';
        }
    }
});
