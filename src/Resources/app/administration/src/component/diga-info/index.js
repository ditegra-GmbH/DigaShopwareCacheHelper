import template from './diga-info.html.twig';

const { Component} = Shopware;

Component.register('diga-info', {
    template,

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    data() {
        return {
            isLoading: false
        };
    },

    created() {
    },

    methods: {
    }
})