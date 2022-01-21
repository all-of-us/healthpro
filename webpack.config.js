var Encore = require('@symfony/webpack-encore');

// Manually configure the runtime environment if not already configured yet by the "encore" command.
// It's useful when you use tools that rely on webpack.config.js file.
if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    // directory where compiled assets will be stored
    .setOutputPath('web/build/') // Change to public/build/ after Symfony migration is complete
    .setPublicPath('/build')

    /*
     * ENTRY CONFIG
     *
     * Add 1 entry for each "page" of your app
     * (including one that's included on every page - e.g. "app")
     *
     * Each entry will result in one JavaScript file (e.g. app.js)
     * and one CSS file (e.g. app.css) if your JavaScript imports CSS.
     */
    .addEntry('app', './web/assets/js/app.js')
    .addEntry('login', './web/assets/css/login.css')
    .addEntry('workqueue', './web/assets/js/views/workqueue.js')
    .addEntry('workqueue-consents', './web/assets/js/views/WorkQueueConsents.js')
    .addEntry('order-check', './web/assets/js/views/OrderSafetyChecks.js')
    .addEntry('order-create', './web/assets/js/views/CreateOrder.js')
    .addEntry('order-sub', './web/assets/js/views/OrderSubPage.js')
    .addEntry('order-finalize', './web/assets/js/views/FinalizeOrder.js')
    .addEntry('patient-status-import', './web/assets/js/views/PatientStatusImport.js')
    .addEntry('physical-measurements-0.1', './web/assets/js/views/PhysicalEvaluation-0.1.js')
    .addEntry('physical-measurements-0.2', './web/assets/js/views/PhysicalEvaluation-0.2.js')
    .addEntry('physical-measurements-0.3', './web/assets/js/views/PhysicalEvaluation-0.3.js')
    .addEntry('physical-measurements-0.3-blood-donor', './web/assets/js/views/PhysicalEvaluation-0.3-blood-donor.js')
    .addEntry('physical-measurements-0.3-ehr', './web/assets/js/views/PhysicalEvaluation-0.3-ehr.js')
    .addEntry('today', './web/assets/js/views/today.js')
    .addEntry('video', './web/assets/js/views/video.js')
    .addEntry('review', './web/assets/js/views/review.js')
    .addEntry('group-member-remove', './web/assets/js/views/GroupMemberRemove.js')

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
    .autoProvidejQuery()
;

module.exports = Encore.getWebpackConfig();
