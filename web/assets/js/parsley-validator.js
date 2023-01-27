/*
 * Copied from node_modules/parsleyjs/src/extra/validator/comparison.js
 * Modified to remove the import line and add messages
 */

window.parseRequirement = function (requirement) {
    if (isNaN(+requirement)) return parseFloat(jQuery(requirement).val());
    else return +requirement;
};

// Greater than validator
window.Parsley.addValidator("gt", {
    validateString: function (value, requirement) {
        return parseFloat(value) > parseRequirement(requirement);
    },
    messages: {
        en: "This value should be greater than %s"
    },
    priority: 32
});

// Less than validator
window.Parsley.addValidator("lt", {
    validateString: function (value, requirement) {
        return parseFloat(value) < parseRequirement(requirement);
    },
    messages: {
        en: "This value should be less than %s"
    },
    priority: 32
});

var validateDateFormat = function (value) {
    var parts = value.split("/");
    if (parts.length < 3) {
        return false;
    }
    var dt = new Date(parts[2], parts[0] - 1, parts[1]);
    return dt && dt.getMonth() === parseInt(parts[0], 10) - 1 && dt.getFullYear() === parseInt(parts[2]);
};

// Date format validator
window.Parsley.addValidator("dateMdy", {
    validateString: function (value) {
        return validateDateFormat(value);
    },
    messages: {
        en: "Invalid date format."
    },
    priority: 32
});
