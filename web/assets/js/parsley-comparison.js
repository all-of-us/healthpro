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

var validateHeightWeight = function(height, weight) {
  if (height && weight) {
    var bmi = weight / ((height/100) * (height/100));
    bmi = bmi.toFixed(1);
    if (bmi < 5 || bmi > 250) {
      return false;
    }
  }
  return true;
};

// BMI validators
window.Parsley.addValidator('bmiHeight', {
  validateString: function (value, weightSelector) {
    var height = parseFloat(value);
    var weight = parseRequirement(weightSelector);
    return validateHeightWeight(height, weight);
  },
  messages: {
    en: 'Invalid height/weight combination'
  },
  priority: 32
});

window.Parsley.addValidator('bmiWeight', {
  validateString: function (value, heightSelector) {
    var weight = parseFloat(value);
    var height = parseRequirement(heightSelector);
    return validateHeightWeight(height, weight);
  },
  messages: {
    en: 'Invalid height/weight combination'
  },
  priority: 32
});
