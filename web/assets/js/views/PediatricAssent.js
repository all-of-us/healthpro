$(document).ready(function () {
    const $form = $("#pediatricAssentForm");
    const $assentSelect = $("#pediatricAssent");
    const $continueBtn = $("#continueBtn");
    const $acknowledgeInput = $("#acknowledgeNoAssent");
    const $errorMessage = $("#assentErrorMessage");
    const modalElement = document.getElementById("pediatricAssentWarningModal");
    const warningModal = modalElement ? new bootstrap.Modal(modalElement) : null;
    const showNoAssentModalOnLoad = ($form.data("showNoAssentModalOnLoad") || 0).toString() === "1";
    let isAcknowledgingNoAssent = false;

    const resetSubmittingState = () => {
        $form.data("submitting", 0);
        $form.find("button[type=submit], input[type=submit]").css("opacity", 1);
        $form.find(".spinner-border").hide();
    };

    const hideContinueButton = () => {
        $continueBtn.addClass("d-none").prop("disabled", true);
    };

    const showContinueButton = () => {
        $continueBtn.removeClass("d-none").prop("disabled", false);
    };

    const updateUiForSelection = (showModalForNo = false) => {
        const value = ($assentSelect.val() || "").toString();
        if (value === "yes" || value === "unable") {
            showContinueButton();
            return;
        }
        hideContinueButton();
        if (value === "no" && showModalForNo && warningModal) {
            warningModal.show();
        }
    };

    const shouldConfirmNoResponse = () =>
        ($assentSelect.val() || "").toString() === "no" && ($acknowledgeInput.val() || "0").toString() !== "1";

    updateUiForSelection(showNoAssentModalOnLoad);

    $assentSelect.on("change", () => {
        isAcknowledgingNoAssent = false;
        $acknowledgeInput.val("0");
        updateUiForSelection(true);
        if ($errorMessage.length) {
            $errorMessage.addClass("d-none");
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
        isAcknowledgingNoAssent = true;
        $acknowledgeInput.val("1");
        warningModal.hide();
        $form.trigger("submit");
    });

    if (modalElement) {
        modalElement.addEventListener("hidden.bs.modal", () => {
            if (!isAcknowledgingNoAssent && ($assentSelect.val() || "").toString() === "no") {
                $assentSelect.val("");
                $acknowledgeInput.val("0");
                hideContinueButton();
            }
            isAcknowledgingNoAssent = false;
            $assentSelect.trigger("focus");
        });
    }
});
