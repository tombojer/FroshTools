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

        getStoreStateClass(state) {
            const s = state ? state.toLowerCase() : '';
            if (s === 'ready') {
                return 'status-badge status-badge--success';
            }
            if (['setup', 'initializing', 'migrating', 'waiting'].includes(s)) {
                return 'status-badge status-badge--warning';
            }
            return 'status-badge status-badge--error';
        },

        getDeploymentStateClass(state) {
            const s = state ? state.toLowerCase() : '';
            if (s === 'running') {
                return 'status-badge status-badge--success';
            }
            if (s === 'scaling') {
                return 'status-badge status-badge--warning';
            }
            return 'status-badge status-badge--error';
        },

        getScheduledTaskStatusClass(status) {
            if (status === 1) {
                return 'status-badge status-badge--success';
            }
            if (status === 0) {
                return 'status-badge status-badge--warning';
            }
            return 'status-badge status-badge--error';
        },

        getScheduledTaskStatusLabel(status) {
            if (status === 1) {
                return this.$t('frosh-tools.tabs.shopwareOperator.scheduledTask.success');
            }
            if (status === -1) {
                return this.$t('frosh-tools.tabs.shopwareOperator.scheduledTask.failed');
            }
            return this.$t('frosh-tools.tabs.shopwareOperator.scheduledTask.unknown');
        },

        formatTimestamp(timestamp) {
            if (!timestamp) {
                return '-';
            }
            return new Date(timestamp * 1000).toLocaleString();
        },
    }
});
