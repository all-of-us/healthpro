$(document).ready(function () {
    const $form = $("#pediatricAssentForm");
    const $assentSelect = $("#pediatricAssent");
    const $continueBtn = $("#continueBtn");
    const $acknowledgeInput = $("#acknowledgeNoAssent");
    const $errorMessage = $("#assentErrorMessage");
    const modalElement = document.getElementById("pediatricAssentWarningModal");
    const warningModal = modalElement ? new bootstrap.Modal(modalElement) : null;

    const resetSubmittingState = () => {
        $form.data("submitting", 0);
        $form.find("button[type=submit], input[type=submit]").css("opacity", 1);
        $form.find(".spinner-border").hide();
    };

    const toggleContinueButton = () => {
        const hasValue = (($assentSelect.val() || "").toString()).length > 0;
        $continueBtn.prop("disabled", !hasValue);
    };

    const shouldConfirmNoResponse = () =>
        (($assentSelect.val() || "").toString() === "no" &&
            ($acknowledgeInput.val() || "0").toString() !== "1");

    toggleContinueButton();

    $assentSelect.on("change", () => {
        $acknowledgeInput.val("0");
        toggleContinueButton();
        if ($errorMessage.length) {
            $errorMessage.addClass("d-none");
        }
    });

    $continueBtn.on("click", (event) => {
        if (shouldConfirmNoResponse()) {
            event.preventDefault();
            warningModal.show();
        }
    });

    $form.on("submit", (event) => {
        if (shouldConfirmNoResponse()) {
            event.preventDefault();
            resetSubmittingState();
            warningModal.show();
        }
    });

    $("#acknowledgeNoAssentBtn").on("click", () => {
        $acknowledgeInput.val("1");
        warningModal.hide();
        $form.trigger("submit");
    });

    if (modalElement) {
        modalElement.addEventListener("hidden.bs.modal", () => {
            $assentSelect.trigger("focus");
        });
    }
});
