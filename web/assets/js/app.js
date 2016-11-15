/*****************************************************************************
 * DOM is ready
 ****************************************************************************/
$(document).ready(function()
{
    /*************************************************************************
     * Supplement Underscore with a truthy function
     ************************************************************************/
    _["truthy"] = function(val) {
        if (_.isString(val) && val.length === 0) return false;
        // use the PHP convention of string "0" being false
        else if (_.isString(val) && val === "0") return false;
        else if (_.isUndefined(val)) return false;
        else return !!val;
    };
    
    /*************************************************************************
     * Configure Underscore.js template settings
     ************************************************************************/
    // use {{ }} instead of <% %> because the '<' and '>' chars seem to get escaped
    // on jQuery html() calls
    _.templateSettings = {
        interpolate: /\{\{=(.+?)\}\}/g,
        escape: /\{\{-(.+?)\}\}/g,
        evaluate: /\{\{(.+?)\}\}/g
    };
    var _PMITPL = {}; // cache of templates
    // global function for views to use to grab templates
    window.pmiGetTpl = function(tplId) {
        if (!_PMITPL.hasOwnProperty(tplId)) {
            _PMITPL[tplId] = _.template($("#" + tplId).html());
        }
        return _PMITPL[tplId];
    };

    /*************************************************************************
     * Disable click on disabled tabs (prevents unnecessary navigation to #)
     ************************************************************************/
     $('.nav-tabs li.disabled a').on('click', function(e) {
        e.preventDefault();
     });
     
    /*************************************************************************
     * Handle session timeout
     ************************************************************************/
    if (PMI.isLoggedIn) {
        $.sessionTimeout({
            title: "Your session is about to expire!",
            message: "Are you there? For security reasons, inactive sessions will be expired.",
            logoutButton: "Logout",
            keepAliveButton: "Stay Connected",
            countdownBar: true,
            countdownSmart: true,
            keepAliveUrl: PMI.path.keepAlive,
            keepAliveInterval: 20000,
            logoutUrl: PMI.path.logout,
            redirUrl: PMI.path.clientTimeout,
            redirAfter: PMI.sessionTimeout * 1000,
            warnAfter: PMI.sessionTimeout * 1000 - (PMI.sessionWarning * 1000),
            warnAutoClose: false
        });
    }

    /*************************************************************************
     * Display system usage agreement when user first logs in
     ************************************************************************/
    if (!PMI.isUsageAgreed) {
        new PmiConfirmModal({
            title: "FISMA MODERATE ENVIRONMENT",
            dialogClass: "modal-lg",
            titleClass: "text-danger",
            isHTML: true,
            msg: pmiGetTpl("pmiSystemUsageTpl")(),
            btnTextTrue: "Agree",
            onTrue: function() {
                $.post(PMI.path.agreeUsage);
            },
            onFalse: function() {
                window.location = PMI.path.logout;
            }
        });
    }

    /*************************************************************************
     * Plugin for making panels in bootstrap columns equal heights
     ************************************************************************/
    $.fn.equalizePanelHeight = function() {
        var selector = this.selector;
        var equalize = function(selector) {
            // reset heights
            $(selector).each(function() {
                $(this).find('.panel').height('auto');
            });
            // set heights
            $(selector).each(function() {
                var height = 0;
                if ($('#is-xs').is(':visible')) {
                    height = 'auto';
                } else {
                    $(this).find('.panel').each(function() {
                        if ($(this).height() > height) {
                            height = $(this).height();
                        }
                    });
                }
                $(this).find('.panel').each(function() {
                    $(this).height(height);
                });
            });
        };
        equalize(selector);
        $(window).on('resize', _.debounce(function() {
            equalize(selector);
        }, 250));
    };
    $('.row-equal-height').equalizePanelHeight();

    /*************************************************************************
     * Store defaults for datetimepicker
     ************************************************************************/
    PMI.datetimepickerDefaults = {
        toolbarPlacement: 'top',
        sideBySide: true,
        showTodayButton: true,
        showClear: true,
        showClose: true
    };
});
