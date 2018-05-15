/*
 * Copied from node_modules/parsleyjs/src/extra/validator/comparison.js
 * Modified to remove the import line and add messages
 */

var parseRequirement = function (requirement) {
  if (isNaN(+requirement))
    return parseFloat(jQuery(requirement).val());
  else
    return +requirement;
};

// Greater than validator
window.Parsley.addValidator('gt', {
  validateString: function (value, requirement) {
    return parseFloat(value) > parseRequirement(requirement);
  },
  messages: {
    en: 'This value should be greater than %s'
  },
  priority: 32
});

// Less than validator
window.Parsley.addValidator('lt', {
  validateString: function (value, requirement) {
    return parseFloat(value) < parseRequirement(requirement);
  },
  messages: {
    en: 'This value should be less than %s'
  },
  priority: 32
});
