$(document).ready(function () {
    const $assentSelect = $("#pediatricAssent");
    const $continueBtn = $("#continueBtn");
    const $errorMessage = $("#assentErrorMessage");
    const getValues = (dataKey) =>
        ($assentSelect.data(dataKey) || "")
            .toString()
            .split(",")
            .map((value) => value.trim())
            .filter(Boolean);
    const continueValues = getValues("continueValues");
    const errorValues = getValues("errorValues");
    const resetState = () => {
        $continueBtn.addClass("d-none");
        $errorMessage.addClass("d-none");
    };
    resetState();
    $assentSelect.on("change", () => {
        const value = ($assentSelect.val() || "").toString();
        resetState();
        if (continueValues.includes(value)) {
            $continueBtn.removeClass("d-none");
        }
        if (errorValues.includes(value)) $errorMessage.removeClass("d-none");
    });
});
