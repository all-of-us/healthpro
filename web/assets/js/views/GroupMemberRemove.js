$(document).ready(function () {
    var showHideReasonField = function (confirmRemove) {
        if ($(confirmRemove + ":checked").val() === "yes") {
            $(".member-reason").show();
            $("button[type=submit]").show();
        } else {
            $(".member-reason").hide();
            $(".member-last-date").hide();
            $("button[type=submit]").hide();
        }
    };

    var showHideMemberLastDayField = function (removeReason) {
        if ($(removeReason + ":checked").val() === "no") {
            $(".member-last-date").show();
        } else {
            $(".member-last-date").hide();
        }
    };

    var confirmRemove = 'input[name="remove_group_member[confirm]"]';
    showHideReasonField(confirmRemove);
    $(confirmRemove).on("change", function () {
        showHideReasonField(confirmRemove);
    });

    var removeReason = 'input[name="remove_group_member[reason]"]';
    showHideMemberLastDayField(removeReason);
    $(removeReason).on("change", function () {
        showHideMemberLastDayField(removeReason);
    });

    let memberLastDay = document.querySelector("#remove_group_member_memberLastDay");
    bs5DateTimepicker(memberLastDay, {
        maxDate: new Date(),
        format: "MM/dd/yyyy",
        clock: false
    });
});
