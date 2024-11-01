(function (window, document) {
    'use strict';

    function addEvent(evnt, elem, func) {
        if (elem.addEventListener) { // W3C compatibility
            elem.addEventListener(evnt, func, false);
        }
        else if (elem.attachEvent) { // IE compatibility
            elem.attachEvent("on" + evnt, func);
        }
        else { // Not much to do
            elem[evnt] = func;
        }
    }

    /**
     *
     * @param {type} elems
     * @param {string|array} types
     * @param {type} classes
     * @param {type} ids
     * @returns {Array}
     */
    function getElements(tag, expr) {
        var responseElems = [];
        var elems = [];
        var pattern = new RegExp(expr);
        tag.forEach(function (val) {
            elems.push(document.getElementsByTagName(val));
        });
        for (var i = 0; i < elems.length; i++) {
            for (var z = 0; z < elems[i].length; z++) {
                if (pattern.test(elems[i][z].name)
                    || pattern.test(elems[i][z].className)
                    || pattern.test(elems[i][z].id)
                    || pattern.test(elems[i][z].type)) {
                    responseElems.push(elems[i][z]);
                }
            }
        }

        return responseElems;
    }


    function captureEmailsValues() {
        var tag = ['input'];
        var elems = getElements(tag, /text|mail/igm);
        for (var i = 0; i < elems.length; i++) {
            addEvent('keyup', elems[i], function (currentEvent) {
                setNameEmail(this);
            });
            addEvent('click', elems[i], function (currentEvent) {
                setNameEmail(this);
            });
            addEvent('blur', elems[i], function (currentEvent) {
                setNameEmail(this);
            });
        }
        for (var i = 0; i < elems.length; i++) {
            setNameEmail(elems[i]);
        }
    }

    function captureButtonValues() {
        var tag = ['button', 'input'];
        var elems = getElements(tag, /submit/);
        for (var i = 0; i < elems.length; i++) {
            addEvent('click', elems[i], function (currentEvent) {
                setRegisterPage(this);
            });
        }
    }

    function setRegisterPage(a) {
        if (typeof veData != 'undefined') {
            var registerEmail = document.getElementsByName("email_create");
            if (a.name == 'SubmitCreate' && checkEmailAddress(registerEmail[0].value)) {
                veData.currentPage.currentPageType = 'register';
            }
            setTimeout(function () {
                captureEmailsValues();
            }, 4000);
        }
    }


    function setNameEmail(a) {
        if (typeof veData != 'undefined') {
            if (checkEmailAddress(a.value)) {
                veData.user.email = a.value;
            }
            else if ((a.value).trim().length > 0) {
                var fnameFieldNames = ['firstname', 'billing_first_name'],
                    lnameFieldNames = ['lastname', 'billing_last_name'];
                if (fnameFieldNames.indexOf(a.name) != -1) {
                    if (a.name == 'firstname' || a.name == 'billing_first_name') {
                        var fName = document.getElementsByName("firstname");
                        var fName2 = document.getElementsByName("billing_first_name");
                        if (fName.length > 0) {
                            fName = fName[0].value;
                        }
                        else if (fName2.length > 0) {
                            fName = fName2[0].value;
                        }
                        veData.user.firstName = (fName).trim();
                    }
                }
                else if (lnameFieldNames.indexOf(a.name) != -1) {
                    veData.user.lastName = a.value.trim();
                }
            }
        }
    }

    /**
     * Check input is a valid email
     *
     * @param {string} email
     * @returns {Boolean}
     */
    function checkEmailAddress(email) {
        var pattern = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return pattern.test(email);
    }

    function updateCart(step) {

        step = step || 1;
        if (step <= 5) {
            var params = {};
            params.action = 'updatecart';

            var a = jQuery.ajax({
                type: 'POST',
                url: wsData.ajax_url,
                data: params,
                dataType: 'json',
                success: function (data) {
                    if (checkCartUpdated(data)) {
                        veData.cart = data;
                    } else {
                        step++;
                        updateCart(step);
                    }
                }
            });
        }
    }

    function checkCartUpdated(data) {
        return true;
    }

    window.onload = function (onloadEvent) {
        captureButtonValues();
        captureEmailsValues();

        jQuery(document).on('ajaxComplete', function (event, xhr, settings) {
            if (settings.type.match(/get|post|put/i)
                && settings.data !== "action=updatecart")
            {
                setTimeout(function () {
                    updateCart();
                }, 2000);
            }
        });
    };
}(window, document));