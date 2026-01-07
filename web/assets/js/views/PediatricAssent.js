$(document).ready(function () {
    const $assentSelect = $("#pediatricAssent");
    const $continueBtn = $("#continueBtn");
    const $errorMessage = $("#assentErrorMessage");
    const resetState = () => {
        $continueBtn.addClass("d-none");
        $errorMessage.addClass("d-none");
    };
    resetState();
    $assentSelect.on("change", () => {
        const value = $assentSelect.val();
        resetState();
        if (value === "no") $errorMessage.removeClass("d-none");
        if (value === "yes") $continueBtn.removeClass("d-none");
    });
});
