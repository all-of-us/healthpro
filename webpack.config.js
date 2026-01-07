var Encore = require("@symfony/webpack-encore");

// Manually configure the runtime environment if not already configured yet by the "encore" command.
// It's useful when you use tools that rely on webpack.config.js file.
if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || "dev");
}

Encore
    // directory where compiled assets will be stored
    .setOutputPath("web/build/") // Change to public/build/ after Symfony migration is complete
    .setPublicPath("/build")

    /*
     * ENTRY CONFIG
     *
     * Add 1 entry for each "page" of your app
     * (including one that's included on every page - e.g. "app")
     *
     * Each entry will result in one JavaScript file (e.g. app.js)
     * and one CSS file (e.g. app.css) if your JavaScript imports CSS.
     */
    .addEntry("app", "./web/assets/js/app.js")
    .addEntry("login", "./web/assets/js/views/Login.js")
    .addEntry("admin-edit-site", "./web/assets/js/views/AdminEditSite.js")
    .addEntry("biobank-order-quanum", "./web/assets/js/views/BiobankOrderQuanum.js")
    .addEntry("biobank-order", "./web/assets/js/views/BiobankOrder.js")
    .addEntry("biobank-participant", "./web/assets/js/views/BiobankParticipant.js")
    .addEntry("export-warning-modal", "./web/assets/js/views/ExportWarningModal.js")
    .addEntry("order-check", "./web/assets/js/views/OrderSafetyChecks.js")
    .addEntry("order-check-pediatric", "./web/assets/js/views/PediatricOrderSafetyChecks.js")
    .addEntry("pediatric-assent", "./web/assets/js/views/PediatricAssent.js")
    .addEntry("order-create", "./web/assets/js/views/CreateOrder.js")
    .addEntry("order-sub", "./web/assets/js/views/OrderSubPage.js")
    .addEntry("order-sub-bs5", "./web/assets/js/views/OrderSubPage-bs5.js")
    .addEntry("order-finalize", "./web/assets/js/views/FinalizeOrder.js")
    .addEntry("patient-status-import", "./web/assets/js/views/PatientStatusImport.js")
    .addEntry("physical-measurements-0.1", "./web/assets/js/views/PhysicalEvaluation-0.1.js")
    .addEntry("physical-measurements-0.2", "./web/assets/js/views/PhysicalEvaluation-0.2.js")
    .addEntry("physical-measurements-0.3", "./web/assets/js/views/PhysicalEvaluation-0.3.js")
    .addEntry("physical-measurements-0.3-blood-donor", "./web/assets/js/views/PhysicalEvaluation-0.3-blood-donor.js")
    .addEntry("physical-measurements-0.3-ehr", "./web/assets/js/views/PhysicalEvaluation-0.3-ehr.js")
    .addEntry("physical-measurements-0.3-peds", "./web/assets/js/views/PhysicalEvaluation-0.3-peds.js")
    .addEntry("physical-measurements-0.3-peds-weight", "./web/assets/js/views/PhysicalEvaluation-0.3-peds.js")
    .addEntry("today", "./web/assets/js/views/today.js")
    .addEntry("video", "./web/assets/js/views/video.js")
    .addEntry("review", "./web/assets/js/views/review.js")
    .addEntry("group-member-remove", "./web/assets/js/views/GroupMemberRemove.js")
    .addEntry("participant-lookup", "./web/assets/js/views/ParticipantLookup.js")
    .addEntry("settings", "./web/assets/js/views/Settings.js")
    .addEntry("problem-reports", "./web/assets/js/views/ProblemReports.js")
    .addEntry("participant", "./web/assets/js/views/Participant.js")
    .addEntry("order-collect", "./web/assets/js/views/OrderCollect.js")
    .addEntry("order-process", "./web/assets/js/views/OrderProcess.js")
    .addEntry("order-requisition", "./web/assets/js/views/OrderRequisition.js")
    .addEntry("order-print-labels", "./web/assets/js/views/OrderPrintLabels.js")
    .addEntry("order-modify", "./web/assets/js/views/OrderModify.js")
    .addEntry("measurements", "./web/assets/js/views/Measurements.js")
    .addEntry("measurement-blood-donor-check", "./web/assets/js/views/MeasurementBloodDonorCheck.js")
    .addEntry("measurement-pediatric-assent-check", "./web/assets/js/views/MeasurementPediatricAssentCheck.js")
    .addEntry("incentive", "./web/assets/js/views/Incentive.js")
    .addEntry("id-verification", "./web/assets/js/views/IdVerification.js")
    .addEntry("deceased-report", "./web/assets/js/views/DeceasedReport.js")
    .addEntry("incentive-import", "./web/assets/js/views/IncentiveImport.js")
    .addEntry("id-verification-import", "./web/assets/js/views/IdVerificationImport.js")
    .addEntry("on-site-patient-status", "./web/assets/js/views/OnSitePatientStatus.js")
    .addEntry("on-site-incentive-tracking", "./web/assets/js/views/OnSiteIncentiveTracking.js")
    .addEntry("on-site-id-verification", "./web/assets/js/views/OnSiteIdVerification.js")
    .addEntry("feature-notification", "./web/assets/js/views/FeatureNotification.js")
    .addEntry("deceased-report-new", "./web/assets/js/views/DeceasedReportNew.js")
    .addEntry("deceased-report-review", "./web/assets/js/views/DeceasedReportReview.js")
    .addEntry("notice-edit", "./web/assets/js/views/NoticeEdit.js")
    .addEntry("program-hpo", "./web/assets/js/views/ProgramHpo.js")
    .addEntry("datatable", "./web/assets/js/views/Datatable.js")
    .addEntry("nph-order-create", "./web/assets/js/views/NphOrderCreate.js")
    .addEntry("nph-order", "./web/assets/js/views/NphOrder.js")
    .addEntry("nph-participant-summary", "./web/assets/js/views/NphParticipantSummary.js")
    .addEntry("nph-sample-finalize", "./web/assets/js/views/NphSampleFinalize.js")
    .addEntry("nph-order-modify", "./web/assets/js/views/NphOrderModify.js")
    .addEntry("nph-review", "./web/assets/js/views/NphReview.js")
    .addEntry("nph-biobank-review", "./web/assets/js/views/NphBiobankReview.js")
    .addEntry("nph-dlw", "./web/assets/js/views/NphDlw.js")
    .addEntry("nph-quick-view", "./web/assets/js/views/NphQuickView.js")
    .addEntry("nph-order-management", "./web/assets/js/views/NphOrderManagement.js")

    .splitEntryChunks()
    .enableSingleRuntimeChunk()

    /*
     * FEATURE CONFIG
     *
     * Enable & configure other features below. For a full
     * list of features, see:
     * https://symfony.com/doc/current/frontend.html#adding-more-features
     */
    .cleanupOutputBeforeBuild()
    .enableSourceMaps(!Encore.isProduction())
    // enables hashed filenames (e.g. app.abc123.css)
    .enableVersioning(Encore.isProduction())

    // uncomment if you're having problems with a jQuery plugin
    .autoProvidejQuery();

module.exports = Encore.getWebpackConfig();
