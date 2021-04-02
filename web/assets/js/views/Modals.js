const _ = require('underscore');

/**
 * Extendable Backbone views that use Bootstrap modals for confirmations and
 * alerts, along with convenience functions pmiConfirm() and pmiAlert().
 */

/*****************************************************************************
 * Enable simultaneous modals in Bootstrap
 * http://stackoverflow.com/a/24914782/1402028
 ****************************************************************************/
$(document).on('show.bs.modal', '.modal', function () {
    var el = $(this);
    setTimeout(function() {
        var zIndex = 1050 + (10 * $(".modal-backdrop").length);
        el.css('z-index', zIndex);
        $('.modal-backdrop').not('.modal-stack').css('z-index', zIndex - 1).addClass('modal-stack');
    }, 0);
});
$(document).on('hidden.bs.modal', '.modal', function () {
    $('.modal:visible').length && $(document.body).addClass('modal-open');
});

/*****************************************************************************
 * Generic confirmation modal
 ****************************************************************************/
window.PmiConfirmModal = Backbone.View.extend({
    _msg: "",
    _isHTML: false,
    _title: "Please Confirm",
    _onTrue: function() {},
    _onFalse: function() {},
    _onClose: function() {},
    _btnTextTrue: "OK",
    _btnTextFalse: "Cancel",
    _dialogClass: "",
    _titleClass: "",
    _tplId: "pmiConfirmTpl",
    _showX: false,
    _showOk: true,
    constructor: function(options) {
        if (options.hasOwnProperty("msg") &&
            typeof options.msg === "string")
        {
            this._msg = options.msg;
        }
        if (options.hasOwnProperty("isHTML") &&
            typeof options.isHTML === "boolean")
        {
            this._isHTML = options.isHTML;
        }
        if (options.hasOwnProperty("title") &&
            typeof options.title === "string")
        {
            this._title = options.title;
        }
        if (options.hasOwnProperty("onTrue") &&
            _.isFunction(options.onTrue))
        {
            this._onTrue = options.onTrue;
        }
        if (options.hasOwnProperty("onFalse") &&
            _.isFunction(options.onFalse))
        {
            this._onFalse = options.onFalse;
        }
        if (options.hasOwnProperty("onClose") &&
            _.isFunction(options.onClose))
        {
            this._onClose = options.onClose;
        }
        if (options.hasOwnProperty("btnTextTrue") &&
            typeof options.btnTextTrue === "string")
        {
            this._btnTextTrue = options.btnTextTrue;
        }
        if (options.hasOwnProperty("btnTextFalse") &&
            typeof options.btnTextFalse === "string")
        {
            this._btnTextFalse = options.btnTextFalse;
        }
        if (options.hasOwnProperty("dialogClass") &&
            typeof options.dialogClass === "string")
        {
            this._dialogClass = options.dialogClass;
        }
        if (options.hasOwnProperty("titleClass") &&
            typeof options.titleClass === "string")
        {
            this._titleClass = options.titleClass;
        }
        if (options.hasOwnProperty("showX") &&
            _.isBoolean(options.showX))
        {
            this._showX = options.showX;
        }
        if (options.hasOwnProperty("showOk") &&
            _.isBoolean(options.showOk))
        {
            this._showOk = options.showOk;
        }
        Backbone.View.prototype.constructor.call(this, options);
    },
    initialize: function() { this.render(); },
    events: {
        "click .pmi-confirm-ok": "confirm",
        "click .pmi-confirm-cancel": "cancel",
        "click .pmi-x-out": "cancel"
    },
    confirm: function() {
        var closeModal = this._onTrue(this);
        // if the output is undefined then assume the function isn't being
        // used to control the modal
        if (closeModal || _.isUndefined(closeModal)) {
            this.$el.modal("hide");
            this.shutdown();
        }
    },
    toggleValid: function(isValid) {
        this.$(".pmi-confirm-err").toggle(!isValid);
        this.$(".pmi-confirm-ok").prop("disabled", !isValid);
    },
    cancel: function() {
        this._onFalse(this);
        this.$el.modal("hide");
        this.shutdown();
    },
    shutdown: function() {
        var that = this;
        this._onClose();
        this.undelegateEvents();
        // delay removal from DOM to give BS modal time to finish hiding
        _.delay(function() { that.remove(); }, 1500);
    },
    setMyElement: function() {
        // create the element we'll use for the dialog and append to DOM
        var el = $(pmiGetTpl(this._tplId)());
        $("body").append(el);
        this.setElement(el);
    },
    render: function() {
        this.setMyElement();
        this.$(".pmi-confirm-ok").text(this._btnTextTrue);
        this.$(".pmi-confirm-cancel").text(this._btnTextFalse);
        this.$(".modal-title").text(this._title);
        if (this._isHTML) this.$(".modal-body").html(this._msg);
        else this.$(".modal-body").text(this._msg);
        if (this._showX) this.$(".pmi-x-out").removeClass("hidden");
        if (!this._showOk) this.$(".pmi-confirm-ok").addClass("hidden");
        
        // reset the dialog class
        this.$(".modal-dialog").attr("class", "modal-dialog");
        // then add any additional classes
        if (this._dialogClass.length > 0)
            this.$(".modal-dialog").addClass(this._dialogClass);
        // reset the title class
        this.$(".modal-title").attr("class", "modal-title");
        // then add any additional classes
        if (this._titleClass.length > 0)
            this.$(".modal-title").addClass(this._titleClass);
        
        this.$el.modal({backdrop: "static"});
    }
});

// convenience function for confirm modal
window.pmiConfirm = function(msg, onTrue, onFalse) {
    new PmiConfirmModal({msg: msg, onTrue: onTrue, onFalse: onFalse});
};

/*****************************************************************************
 * Generic alert modal
 ****************************************************************************/
window.PmiAlertModal = PmiConfirmModal.extend({
    _msg: "",
    _title: "Please Note",
    _tplId: "pmiAlertTpl",
    events: {
        "click .pmi-confirm-ok": "confirm",
        "click .pmi-x-out": "confirm"
    }
});

// convenience function for confirm modal
window.pmiAlert = function(msg, isHTML) {
    new PmiAlertModal({msg: msg, isHTML: isHTML});
};
