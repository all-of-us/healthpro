$(document).ready(function () {
    const $assentSelect = $('select[name="form[assent]"]');
    const $assentContinue = $("#assent_continue");
    const $assentMessage = $("#assent_message");
    let toggleAssentType = function () {
        const assentValue = $assentSelect.val();
        $assentContinue.add($assentMessage).addClass("d-none");
        if (assentValue === "yes") {
            $assentContinue.removeClass("d-none");
        } else if (assentValue === "no") {
            $assentMessage.removeClass("d-none");
        }
    };
    toggleAssentType();
    $assentSelect.on("change", toggleAssentType);
});
