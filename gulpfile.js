var ASSETS_DIR = './web/assets'; // adjust DeployCommand's RetireJS check if this changes
var NODE_DIR = './node_modules';
var ASSETS = {
    'js': [
        NODE_DIR + '/jquery/dist/jquery.js',
        NODE_DIR + '/moment/moment.js',
        NODE_DIR + '/bootstrap/dist/js/bootstrap.js',
        NODE_DIR + '/underscore/underscore.js',
        NODE_DIR + '/backbone/backbone.js',
        NODE_DIR + '/eonasdan-bootstrap-datetimepicker/build/js/bootstrap-datetimepicker.min.js',
        NODE_DIR + '/parsleyjs/dist/parsley.js',
        NODE_DIR + '/datatables.net/js/jquery.datatables.js',
        NODE_DIR + '/datatables.net-bs/js/dataTables.bootstrap.js',
        NODE_DIR + '/datatables.net-responsive/js/dataTables.responsive.js',
        NODE_DIR + '/datatables.net-responsive-bs/js/responsive.bootstrap.js',
        NODE_DIR + '/datatables.net-buttons/js/dataTables.buttons.js',
        NODE_DIR + '/datatables.net-buttons/js/buttons.colVis.js',
        NODE_DIR + '/datatables.net-buttons-bs/js/buttons.bootstrap.js',
        NODE_DIR + '/jsbarcode/dist/barcodes/JsBarcode.code128.min.js',
        ASSETS_DIR + '/js/parsley-comparison.js',
        ASSETS_DIR + '/js/bootstrap-session-timeout.js',
        ASSETS_DIR + '/js/app.js',
        ASSETS_DIR + '/js/jstz.min.js',
        ASSETS_DIR + '/js/views/*'
    ],
    'css': [
        NODE_DIR + '/bootstrap/dist/css/bootstrap.min.css',
        NODE_DIR + '/font-awesome/css/font-awesome.min.css',
        NODE_DIR + '/eonasdan-bootstrap-datetimepicker/build/css/bootstrap-datetimepicker.min.css',
        NODE_DIR + '/datatables.net-bs/css/dataTables.bootstrap.css',
        NODE_DIR + '/datatables.net-responsive-bs/css/responsive.bootstrap.css',
        NODE_DIR + '/datatables.net-buttons-bs/css/buttons.bootstrap.css'
    ],
    'csslocal': [
        ASSETS_DIR + '/css/app.css'
    ],
    'fonts': [
        NODE_DIR + '/bootstrap/dist/fonts/*',
        NODE_DIR + '/font-awesome/fonts/*'
    ]
};

var gulp = require('gulp');
var concat = require('gulp-concat');
var rename = require('gulp-rename');
var uglify = require('gulp-uglify');
var cssnano = require('gulp-cssnano');
var cssconcat = require('gulp-concat-css');
var sourcemaps = require('gulp-sourcemaps');
var merge = require('merge-stream');
var browserSync = require('browser-sync').create();

gulp.task('compile-js', function() {
    var destDir = ASSETS_DIR + '/dist/js';
    return gulp.src(ASSETS.js)
        .pipe(concat('all.js'))
        .pipe(gulp.dest(destDir))
        .pipe(sourcemaps.init())
            .pipe(uglify())
            .pipe(rename('all.min.js'))
        .pipe(sourcemaps.write('.'))
        .pipe(gulp.dest(destDir));
});

gulp.task('compile-css', function() {
    var destDir = ASSETS_DIR + '/dist/css';
    var localDestDir = ASSETS_DIR + '/dist';
    var cssDir = 'css';

    // Compile 3rd party dependencies
    var lib = gulp.src(ASSETS.css)
        .pipe(concat('lib.css'))
        .pipe(gulp.dest(destDir))
        .pipe(cssnano({zindex: false}))
        .pipe(rename('lib.min.css'))
        .pipe(gulp.dest(destDir));

    // Compile our CSS (using concat-css to rebase urls)
    var local = gulp.src(ASSETS.csslocal)
        .pipe(cssconcat(cssDir + '/app.css'))
        .pipe(gulp.dest(localDestDir))
        .pipe(sourcemaps.init())
            .pipe(cssnano({zindex: false}))
            .pipe(rename(cssDir + '/app.min.css'))
        .pipe(sourcemaps.write('.'))
        .pipe(gulp.dest(localDestDir));

    return merge(lib, local);
});

gulp.task('compile-fonts', function() {
    var destDir = ASSETS_DIR + '/dist/fonts';
    return gulp.src(ASSETS.fonts)
        .pipe(gulp.dest(destDir));
});

gulp.task('compile', gulp.parallel(
    'compile-js',
    'compile-css',
    'compile-fonts'
));

// re-compile when files change
gulp.task('watch', function() {
    gulp.watch(ASSETS.js, gulp.parallel('compile-js'));
    gulp.watch(ASSETS.csslocal, gulp.parallel('compile-css'));
});

// default task is to compile everything and then start watching
gulp.task('default', gulp.series(
    'compile',
    'watch'
));

var initializeBrowser = function(done) {
    var url = process.argv[4];
    browserSync.init({
        proxy: url
    });
    done();
};

var reloadBrowser = function(done) {
    browserSync.reload();
    done();
};

// initialize browser sync
gulp.task('initialize-browser-sync', gulp.parallel(initializeBrowser));

// recompile and reload browser when files change
gulp.task('watch-browser-sync', function() {
    gulp.watch(ASSETS.js, gulp.series('compile-js', reloadBrowser));
    gulp.watch(ASSETS.csslocal, gulp.series('compile-css', reloadBrowser));
});

// custom task to compile everything, initialize browser sync and then start watching
gulp.task('browser-sync', gulp.series(
    'compile',
    'initialize-browser-sync',
    'watch-browser-sync'
));
