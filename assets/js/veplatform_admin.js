vePlatformAdmin = function () {
    __checkResponse = function (response) {
        if (wsData.isInstallFlow) {
            return (typeof response != undefined && response != null &&
            typeof response.Token != undefined && response.Token != null &&
            typeof response.URLTag != undefined && response.URLTag != null &&
            typeof response.URLPixel != undefined && response.URLPixel != null);
        }
        else {
            return (typeof response != undefined && response != null &&
            typeof response.HtmlView != undefined && response.HtmlView != null);
        }
    };

    __markInstall = function (response) {
        params = {};
        params.action = 'vpinstalled';
        params.response = response;

        jQuery.ajax({
            type: 'POST',
            dataType: 'json',
            url: wsData.ajax_url,
            data: params,
            success: function (response) {
                //no other action is needed
            }
        });
    };

    __deactivatePlugin = function () {
        params = {};
        params.action = 'deactivatevp';

        jQuery.ajax({
            type: 'POST',
            dataType: 'json',
            url: wsData.ajax_url,
            data: params,
            success: function (response) {

                if (response.status == 'ok') {
                    var html = '<div class="notice error"><p>' + response.msg + '</p></div>';
                    jQuery('#ve_loading').html(html).addClass('msg');

                    setTimeout(function () {
                        location.href = response.redirectUrl;
                    }, 6000);
                }
            }
        });
    };

    __logAction = function (msg, isError) {
        params = {};
        params.action = 'logaction';
        params.message = msg;
        if (typeof isError == 'undefined' || isError == null) {
            params.isError = true;
        }

        jQuery.ajax({
            type: 'POST',
            dataType: 'json',
            url: wsData.ajax_url,
            data: params,
            success: function (response) {
            }
        });
    };

    __setParams = function () {
        var paramList = {};

        paramList.domain = wsData.domain;
        paramList.language = wsData.language;
        paramList.email = wsData.email;
        paramList.merchant = wsData.merchant;
        paramList.contactname = wsData.contactName;
        paramList.country = wsData.country;
        paramList.phone = wsData.phone;
        paramList.currency = wsData.currency;
        paramList.version = wsData.version;
        paramList.ecommerce = wsData.ecommerce;
        paramList.isInstallFlow = wsData.isInstallFlow ? "true" : "false";
        paramList.moduleVersion = wsData.moduleVersion;

        return paramList;
    };

    __sleepFor = function (sleepDuration) {
        var now = new Date().getTime();
        while (new Date().getTime() < now + sleepDuration) { /* do nothing */
        }
    };

    __installWS = function () {
        var params = __setParams();

        __logAction('Start - Call WS endpoint ' + wsData.apiURL, false);

        jQuery.ajax({
            type: 'POST',
            dataType: 'json',
            url: wsData.apiURL,
            data: params,
            success: function (response) {
                if (!__checkResponse(response)) {
                    __logAction('Response, tag or pixel are empty', true);
                    if (wsData.isInstallFlow) {
                        __logAction('Error on install', true);
                        __deactivatePlugin();
                    }
                    else {
                        __errorSettings();
                    }
                }
                else {
                    jQuery('#veinteractive_main').html(response.HtmlView);
                    __sleepFor(2000);
                    jQuery('#ve_loading').addClass('hidden');
                    jQuery('#veinteractive_main').removeClass('hidden');

                    __logAction('End - Call to WS was successful', true);
                    __markInstall(response);
                }
            },
            error: function (xhr, status) {
                if ((xhr.readyState < 4) || wsData.isInstallFlow) {
                    __logAction('Error on install or install flow was interrupted.', true);
                    __deactivatePlugin();
                }
                else {
                    __errorSettings();
                }
            }
        });


    };

    __errorSettings = function () {
        __logAction('Error on settings', true);
        var html = '<div class="notice error"><p>' + wsData.settingsError + '</p></div>';
        jQuery('#ve_loading').html(html).addClass('msg');

        setTimeout(function () {
            location.href = wsData.pluginsUrl;
        }, 6000);
    };

    this.init = function () {
        __installWS();
    };

};

vpAdmin = new vePlatformAdmin();
vpAdmin.init();