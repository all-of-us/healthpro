const $ = require("jquery");
const _ = require("underscore/underscore-node");
const jstz = require("./jstz.min");
/*****************************************************************************
 * DOM is ready
 ****************************************************************************/
$(document).ready(function () {
    /*************************************************************************
     * Security fix: https://github.com/jquery/jquery/issues/2432#issuecomment-140038536
     * Can be removed after upgrading to jQuery 3.x
     ************************************************************************/
    $.ajaxSetup({
        contents: {
            javascript: false
        }
    });

    /*************************************************************************
     * Supplement Underscore with a truthy function
     ************************************************************************/
    _["truthy"] = function (val) {
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
    window.pmiGetTpl = function (tplId) {
        /* eslint-disable security/detect-object-injection */
        if (!_PMITPL.hasOwnProperty(tplId)) {
            _PMITPL[tplId] = _.template($("#" + tplId).html());
        }
        return _PMITPL[tplId];
        /* eslint-enable security/detect-object-injection */
    };

    /*************************************************************************
     * Disable click on disabled tabs (prevents unnecessary navigation to #)
     ************************************************************************/
    $(".nav-tabs li.disabled a").on("click", function (e) {
        e.preventDefault();
    });

    /*************************************************************************
     * Disable forms being submitted via enter/return key on any text input
     * inside an element with the .form-disable-enter class
     ************************************************************************/
    $("form.disable-enter input:text").on("keypress keyup", function (e) {
        if (e.which == 13) {
            e.preventDefault();
            return false;
        }
    });

    /*************************************************************************
     * Disable forms being double-submitted by disabling any submit buttons
     * while submitting
     ************************************************************************/
    $("form.prevent-resubmit").on("submit", function (e) {
        var form = $(e.currentTarget);
        if (form.data("submitting")) {
            e.preventDefault();
            return;
        } else {
            form.data("submitting", 1);
            form.find("button[type=submit], input[type=submit]").css("opacity", 0.5);
            // Show the spinner
            let $button = form.find("button[type=submit]:focus");
            let $spinner = $button.find(".spinner-border");
            $spinner.show();
        }
    });
    // If form submission is stopped by parsley, clear the submitting status and opacity
    window.Parsley.on("form:error", function () {
        $(this.$element).data("submitting", 0);
        $(this.$element).find("button[type=submit], input[type=submit]").css("opacity", 1);
    });

    /*************************************************************************
     * Auto-enable bootstrap tooltips
     ************************************************************************/
    $('[data-toggle="tooltip"]').tooltip();

    /*************************************************************************
     * Handle session timeout
     ************************************************************************/
    if (PMI.isLoggedIn) {
        $.sessionTimeout({
            title: "Your session is about to expire!",
            message: "Are you there? For security reasons, inactive sessions will be expired.",
            logoutButton: "Logout",
            keepAliveButton: "Stay Connected",
            ajaxData: { csrf_token: PMI.keepAliveCsrf },
            countdownBar: true,
            countdownSmart: true,
            keepAliveUrl: PMI.path.keepAlive,
            keepAliveInterval: 20000,
            logoutUrl: PMI.path.logout,
            redirUrl: PMI.path.clientTimeout,
            redirAfter: PMI.sessionTimeout * 1000,
            warnAfter: PMI.sessionTimeout * 1000 - PMI.sessionWarning * 1000,
            warnAutoClose: false,
            onRedir: function (opt) {
                // suppress unsaved warning when user is being logged out
                PMI.markSaved();
                window.location = opt.redirUrl;
            }
        });
    }

    /*************************************************************************
     * Plugin for making panels (or other single-element inside a column)
     * in Bootstrap rows equal heights
     *
     * For example:
     * <div class="row row-equal-height">
     *     <div class="col-sm-6"><div class="panel">...</div></div>
     *     <div class="col-sm-6"><div class="panel">...</div></div>
     * </div>
     *
     * or
     *
     * <div class="row row-equal-height">
     *     <div class="col-sm-6"><a class="btn btn-block">...</a></div>
     *     <div class="col-sm-6"><a class="btn btn-block">...</a></div>
     * </div>
     ************************************************************************/
    $.fn.equalizeColumnHeight = function () {
        var equalize = function (element) {
            element.each(function () {
                // We want the first child of each column in the row
                var columnChildren = $(this).children().children();

                // reset heights
                columnChildren.height("auto");

                // set heights
                var height = 0;
                if ($("#is-xs").is(":visible")) {
                    height = "auto";
                } else {
                    columnChildren.each(function () {
                        if ($(this).is(":visible") && $(this).height() > height) {
                            height = $(this).height();
                        }
                    });
                }
                columnChildren.each(function () {
                    $(this).height(height);
                });
            });
        };
        var self = this;
        equalize(self);
        $(window).on(
            "resize",
            _.debounce(function () {
                equalize(self);
            }, 250)
        );
        $(window).on("pmi.equalize", function () {
            equalize(self);
        });
    };
    $(".row-equal-height").equalizeColumnHeight();

    /*************************************************************************
     * Plugin to initialize datetimepicker and register change event listener
     ************************************************************************/
    $.fn.pmiDateTimePicker = function (options = {}) {
        // datetimepicker documentation: https://eonasdan.github.io/bootstrap-datetimepicker/
        var pickerOptions = Object.assign(
            {
                toolbarPlacement: "top",
                sideBySide: true,
                showTodayButton: true,
                showClear: true,
                showClose: true,
                keyBinds: {
                    up: null,
                    down: null,
                    left: null,
                    right: null,
                    delete: null
                }
            },
            options
        );
        this.datetimepicker(pickerOptions);
        this.on("dp.change", function () {
            PMI.markUnsaved();
        });
    };

    /*************************************************************************
     * Plugin to set value and trigger change event if changed
     ************************************************************************/
    $.fn.valChange = function (val) {
        var triggerChange = this.val() != val;
        this.val(val);
        if (triggerChange) {
            this.trigger("change");
        }
        return this;
    };

    /*************************************************************************
     * Plugin to transform text field into select dropdown with "other" option
     * Usage:
     * $('#form_input').dropdownOther({
     *     'Label 1': 'value_1',
     *     'Label 2': 'value_2'
     * });
     ************************************************************************/
    $.fn.dropdownOther = function (choices) {
        var self = this;
        var select = $('<select class="form-control">');
        var optionSelected = false;
        var other = $('<option value="">-- Other --</option>');
        select.append(other);
        $.each(choices, function (display, value) {
            var option = $("<option>").val(value).text(display);
            if (option.val() === self.val()) {
                option.attr("selected", true);
                optionSelected = true;
            }
            select.append(option);
        });
        if (optionSelected) {
            self.attr("readonly", true);
        }

        var col1 = $('<div class="col-sm-6">');
        var col2 = $('<div class="col-sm-6">');
        var row = $('<div class="row">');
        row.append(col1).append(col2);
        this.before(row);
        col1.append(select);
        col2.append(this);

        select.on("change", function () {
            self.val($(this).val());
            if ($(this).val()) {
                self.attr("readonly", true);
            } else {
                self.attr("readonly", false);
                self.focus();
            }
        });
        return this;
    };

    /*************************************************************************
     * Unsaved changes prompter
     ************************************************************************/
    PMI.hasChanges = false;
    PMI.markUnsaved = function () {
        this.hasChanges = true;
    };
    PMI.markSaved = function () {
        this.hasChanges = false;
    };
    PMI.enableUnsavedPrompt = function (selector) {
        if (typeof selector === "undefined") {
            selector = document;
        }
        $(window).on("beforeunload", function () {
            if (PMI.hasChanges) {
                return "You have unsaved changes on this page that will not be saved if you continue.";
            }
        });
        var handleChangedInput = function () {
            // Mark as unsaved unless element has the class "ignore-unsaved"
            if (!$(this).is(".ignore-unsaved")) {
                PMI.markUnsaved();
            }
        };

        // Mark unsaved on change
        $(selector).on("change", "input, select, textarea", handleChangedInput);

        // Also mark unsaved on keyup or paste since the change event
        // does not fire on text fields until the field loses focus, meaning
        // that text entry followed by browser forward/back/close would be missed
        $(selector).on("keyup paste", "input[type=text], textarea", _.debounce(handleChangedInput, 2000, true));

        // Mark as saved when clicking a submit button
        $(selector).on("click", "button[type=submit]", function () {
            PMI.markSaved();
        });
    };

    PMI.disabledUnsavedPrompt = function () {
        $(window).on("beforeunload", null);
        this.hasChanges = false;
    };

    // Automatically enable unsaved prompt on forms with warn-unsaved class
    if ($("form").hasClass("warn-unsaved")) PMI.enableUnsavedPrompt("form.warn-unsaved");

    /*************************************************************************
     * Time zone detection
     ************************************************************************/
    PMI.browserTimeZone = jstz.determine().name();
    PMI.isTimeZoneDiff =
        PMI.userTimeZone &&
        PMI.browserTimeZone &&
        PMI.browserTimeZone in PMI.timeZones &&
        PMI.userTimeZone != PMI.browserTimeZone;

    if (
        (PMI.userSite || PMI.readOnlyView) &&
        $.inArray(PMI.currentRoute, ["settings"]) === -1 &&
        PMI.isTimeZoneDiff &&
        !PMI.hideTZWarning
    ) {
        var html = '<div class="alert alert-warning">';
        html += '<a href="#" class="close" id="tz_close" data-dismiss="alert" aria-label="close">&times;</a>';
        html += "Your computer's time zone does not appear to match your HealthPro time zone preference. ";
        html += '<a href="' + PMI.path.settings + '">Update preference</a>';
        html += "</div>";
        $("#flash-notices").append(html);
    }

    $("#tz_close").on("click", function (e) {
        $.post(PMI.path.hideTZWarning, {
            csrf_token: PMI.hideTZWarningCsrf
        });
    });

    $(".external-link").on("click", function () {
        var location = $(this).data("href");
        var msg = "<p>You are leaving the DRC HealthPro web application and accessing a non-FISMA destination.</p>";
        msg += "<ul>";
        msg += "<li>Entering of participant information is prohibited at the destination.</li>";
        msg +=
            "<li>This external link provides additional information that is consistent with the intended purpose of HealthPro. DRC cannot attest to the accuracy of a non-DRC site.</li>";
        msg += "</ul>";
        msg +=
            "<p>Linking to a non-DRC site does not constitute endorsement by DRC or any of its employees of the sponsors or information and products presented on the site. You will be subject to the destination site's privacy policy when you follow the link.</p>";
        new PmiConfirmModal({
            title: "Attention",
            msg: msg,
            isHTML: true,
            onTrue: function () {
                window.open(location, "_blank");
            },
            btnTextTrue: "Continue"
        });
    });

    $(".notification-title").on("click", function () {
        let notificationsModal = $("#notificationsModal");
        let modelContent = $("#notificationsModal .modal-content");
        modelContent.html("");
        modelContent.load($(this).data("href"));
        $(notificationsModal).modal("show");
    });

    $("#notifications_mark_all_read").on("click", function () {
        if (parseInt($(this).data("notifications-count")) > 0) {
            $.get($(this).data("href"), function () {
                $("#notifications_count, .notifications-circle").html("");
                $("#notifications_mark_all_read").attr("data-notifications-count", 0);
            });
        }
    });

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
            onTrue: function (modal) {
                $.post(PMI.path.agreeUsage, {
                    csrf_token: modal.$("#csrf_token").val()
                });
            },
            onFalse: function () {
                window.location = PMI.path.logout;
            }
        });
    }
});
