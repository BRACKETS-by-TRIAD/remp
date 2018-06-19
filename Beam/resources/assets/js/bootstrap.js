import Vue2Filters from 'vue2-filters'

global.$ = global.jQuery = require('jquery');

global.Vue = require('vue');
Vue.use(Vue2Filters)

global.moment = require('moment');

global.SmartRangeSelector = require("remp/js/components/SmartRangeSelector.vue");
global.DateTimePicker = require("remp/js/components/DateTimePickerWrapper.vue");
global.RecurrenceSelector = require("./components/RecurrenceSelector.vue");
global.RuleOcurrences = require("./components/RuleOcurrences.vue");
global.FormValidator = require("remp/js/components/FormValidator");
