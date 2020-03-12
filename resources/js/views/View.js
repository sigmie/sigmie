import Vue from 'vue'

export default Vue.extend({
    data() {
        return {
            state: 'fetching'
        }
    },
    beforeMount() {
        this.$watch('state', (newValue, oldValue) => {
            if (oldValue === 'fetching' && newValue !== 'fetching') {
                this.$root.$refs.bar.animate(1.0, [], this.$root.$refs.bar.reset)
            }
        });
    }
})
