<style scoped>
    h4 {
        margin-top: 0;
        margin-bottom: 0;
        font-size: 14px;
    }

    strong {
        font-size: 12px;
    }

    .card-header {
        padding-top: 10px;
        padding-bottom: 0;
    }

    .card-body {
        font-size: 22px;
    }

    .error {
        position: absolute;
        top: 0;
        right: 0;
        width: 20px;
        height: 20px;
        background: red;
        color: #fff;
        text-align: center;
    }

    .preloader-wrapper {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.8);
    }

    .preloader-wrapper .preloader {
        position: absolute;
        top: 50%;
        left: 50%;
        margin-left: -20px;
        margin-top: -20px;
    }

    .info-text {
        position: absolute;
        top: 5px;
        right: 5px;
        z-index: 5;
    }

    .title {
        margin-bottom: 7px;
    }
</style>

<template>
    <div class="card" :class="'card-' + title | slugify">
        <i v-if="infoText" :title="infoText" class="zmdi zmdi-info-outline info-text"></i>

        <div v-if="loading" class="preloader-wrapper">
            <div class="preloader">
                <svg class="pl-circular" viewBox="25 25 50 50">
                    <circle class="plc-path" cx="50" cy="50" r="20" />
                </svg>
            </div>
        </div>
        <div v-if="error" class="error" :title="error">!</div>

        <div class="card-header text-center title">
            {{ title }}
        </div>

        <div class="card-body card-padding-sm text-center">
            <span v-for="(item, index) in values">
                <br v-if="index!==0"/>
                {{ item.value | round(precision) }}&nbsp;{{ item.unit }}
            </span>
        </div>
    </div>
</template>

<script>
    export default {
        props: {
            title: {
                type: String,
                required: true
            },
            // Array of Objects containing units with values
            values: {
                type: Array,
                required: true
            },
            loading: {
                type: Boolean,
                default: true
            },
            precision: {
                type: Number,
                default: 2
            },
            error: {
                type: String
            },
            infoText: {
                type: String
            }
        }
    }
</script>
