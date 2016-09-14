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
     * Disable click on disabled tabs (prevents unnecessary navigation to #)
     ************************************************************************/
     $('.nav-tabs li.disabled a').on('click', function(e) {
        e.preventDefault();
     });
});
