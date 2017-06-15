<style type="text/css">
    .preview-image {
        opacity: 0.3;
    }
    .preview-box {
        position: absolute;
    }
    .cp-value {
        cursor: pointer;
    }

</style>

<template id="banner-form-template">
    <div class="row">
        <div class="col-md-4">
            <h4>Settings</h4>

            <div class="input-group fg-float m-t-30">
                <span class="input-group-addon"><i class="zmdi zmdi-account"></i></span>
                <div class="fg-line">
                    <label for="name" class="fg-label">Name</label>
                    <input v-model="name" class="form-control fg-input" name="name" id="name" type="text">
                </div>
            </div>

            <div class="cp-container">
                <div class="input-group fg-float m-t-30">
                    <span class="input-group-addon"><i class="zmdi zmdi-format-color-text"></i></span>
                    <div class="fg-line dropdown">
                        <label for="Text Color" class="fg-label">Text Color</label>
                        <input v-model="textColor" class="form-control cp-value" data-toggle="dropdown" name="text_color" type="text">

                        <div class="dropdown-menu">
                            <div class="color-picker" data-cp-default="#03A9F4"></div>
                        </div>
                        <i class="cp-value"></i>
                    </div>
                </div>
            </div>

            <div class="cp-container">
                <div class="input-group fg-float m-t-30">
                    <span class="input-group-addon"><i class="zmdi zmdi-format-color-fill"></i></span>
                    <div class="fg-line dropdown">
                        <label for="Background color" class="fg-label">Background Color</label>
                        <input v-model="backgroundColor" class="form-control cp-value" data-toggle="dropdown" name="background_color" type="text">

                        <div class="dropdown-menu">
                            <div class="color-picker"></div>
                        </div>
                        <i class="cp-value"></i>
                    </div>
                </div>
            </div>

            <div class="input-group fg-float m-t-30">
                <span class="input-group-addon"><i class="zmdi zmdi-format-subject"></i></span>
                <div class="fg-line">
                    <label for="HTML text" class="fg-label">HTML Text</label>
                    <textarea v-model="text" class="form-control fg-input" rows="3" name="text" cols="50"></textarea>
                </div>
            </div>

            <div class="input-group fg-float m-t-30">
                <span class="input-group-addon"><i class="zmdi zmdi-format-size"></i></span>
                <div class="fg-line">
                    <label for="Font size" class="fg-label">Font Size</label>
                    <input v-model="fontSize" class="form-control fg-input" name="font_size" type="number">
                </div>
            </div>

            <div class="input-group m-t-30">
                <span class="input-group-addon"><i class="zmdi zmdi-swap"></i></span>
                <div>
                    <div class="row">
                        <div class="col-md-12">
                            <label for="Text alignment" class="fg-label">Transition</label>
                        </div>
                        <div class="col-md-12">
                            <select v-model="transition" class="selectpicker" name="transition">
                                <option value="none">None</option>
                                <option value="fade">Fade</option>
                                <option value="bounce">Bounce</option>
                                <option value="shake">Shake</option>
                                <option value="fade-in-down">Fade in down</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="input-group m-t-30">
                <span class="input-group-addon"><i class="zmdi zmdi-swap"></i></span>
                <div>
                    <div class="row">
                        <div class="col-md-12">
                            <label for="Text alignment" class="fg-label">Text Alignment</label>
                        </div>
                        <div class="col-md-12">
                            <select v-model="textAlign" class="selectpicker" name="text_align">
                                <option v-for="option in alignmentOptions" v-bind:value="option.key">
                                    @{{ option.name }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="input-group m-t-30">
                <span class="input-group-addon"><i class="zmdi zmdi-aspect-ratio-alt"></i></span>
                <div>
                    <div class="row">
                        <div class="col-md-12">
                            <label for="Dimensions" class="fg-label">Dimensions</label>
                        </div>
                        <div class="col-md-12">
                            <select v-model.lazy="dimensions" class="selectpicker" name="dimensions">
                                <option v-for="option in dimensionOptions" v-bind:value="option.key">
                                    @{{ option.name }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="input-group m-t-30">
                <span class="input-group-addon"><i class="zmdi zmdi-photo-size-select-large"></i></span>
                <div>
                    <div class="row">
                        <div class="col-md-12">
                            <label for="Position" class="fg-label">Position</label>
                        </div>
                        <div class="col-md-12">
                            <select v-model.lazy="position" class="selectpicker" name="position">
                                <option v-for="option in positionOptions" v-bind:value="option.key">
                                    @{{ option.name }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="input-group fg-float m-t-30">
                <span class="input-group-addon"><i class="zmdi zmdi-link"></i></span>
                <div class="fg-line">
                    <label for="Target URL" class="fg-label">Target URL</label>
                    <input v-model="targetUrl" class="form-control fg-input" name="target_url" type="text">
                </div>
            </div>

            <div class="input-group fg-float m-t-30">
                <span class="input-group-addon"><i class="zmdi zmdi-timer"></i></span>
                <div class="fg-line">
                    <label for="Display delay" class="fg-label">Display delay (milliseconds)</label>
                    <input v-model="displayDelay" class="form-control fg-input" name="display_delay" type="number">
                </div>
            </div>

            <div class="input-group fg-float m-t-30">
                <span class="input-group-addon"><i class="zmdi zmdi-time-interval"></i></span>
                <div class="fg-line">
                    <label for="Automatic close" class="fg-label">Automatic close after (milliseconds)</label>
                    <input v-model="closeTimeout" class="form-control fg-input" name="close_timeout" type="number">
                </div>
            </div>

            <div class="input-group fg-float m-t-30 checkbox">
                <label class="m-l-15">
                    Ability to close banner manually
                    <input v-model="closeable" value="1" name="closeable" type="checkbox">
                    <i class="input-helper"></i>
                </label>
            </div>


            <div class="input-group m-t-20">
                <div class="fg-line">
                    <button class="btn btn-info waves-effect" type="submit"><i class="zmdi zmdi-mail-send"></i> Save</button>
                </div>
            </div>

        </div>
        <div class="col-md-7 col-md-offset-1">
            <h4>
                Preview
                <span class="btn btn-default m-l-20" v-on:click="show = !show">Toggle banner</span>
            </h4>

            <div class="row p-relative" style="width: 560px; height: 700px">
                <img src="http://rempcampaign.local/assets/img/website_mockup.png" class="preview-image" alt="Mockup" height="700px">
                <div id="banner-preview">
                    <banner-preview></banner-preview>
                </div>
            </div>

        </div>
    </div>
</template>
