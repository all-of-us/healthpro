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
    .addEntry('app', './web/assets/js/app.webpack.js')
    .addEntry('workqueue', './web/assets/js/views/workqueue.js')
    .addEntry('order-check', './web/assets/js/views/OrderSafetyChecks.js')
    .addEntry('order-create', './web/assets/js/views/CreateOrder.js')
    .addEntry('order-sub', './web/assets/js/views/OrderSubPage.js')
    .addEntry('patient-status-import', './web/assets/js/views/PatientStatusImport.js')
    .addEntry('physical-measurements-0.1', './web/assets/js/views/PhysicalEvaluation-0.1.js')
    .addEntry('physical-measurements-0.2', './web/assets/js/views/PhysicalEvaluation-0.2.js')
    .addEntry('physical-measurements-0.3', './web/assets/js/views/PhysicalEvaluation-0.3.js')

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
