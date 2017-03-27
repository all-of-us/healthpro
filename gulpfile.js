var ASSETS_DIR = './web/assets'; // adjust DeployCommand's RetireJS check if this changes
var BOWER_DIR = './bower_components';
var ASSETS = {
    'js': [
        BOWER_DIR + '/jquery/dist/jquery.js',
        BOWER_DIR + '/moment/moment.js',
        BOWER_DIR + '/bootstrap/dist/js/bootstrap.js',
        BOWER_DIR + '/underscore/underscore.js',
        BOWER_DIR + '/backbone/backbone.js',
        BOWER_DIR + '/eonasdan-bootstrap-datetimepicker/build/js/bootstrap-datetimepicker.min.js',
        BOWER_DIR + '/parsleyjs/dist/parsley.js',
        BOWER_DIR + '/datatables.net/js/jquery.datatables.js',
        BOWER_DIR + '/datatables.net-bs/js/dataTables.bootstrap.js',
        BOWER_DIR + '/datatables.net-responsive/js/dataTables.responsive.js',
        BOWER_DIR + '/datatables.net-responsive-bs/js/responsive.bootstrap.js',
        BOWER_DIR + '/datatables.net-buttons/js/dataTables.buttons.js',
        BOWER_DIR + '/datatables.net-buttons/js/buttons.colVis.js',
        BOWER_DIR + '/datatables.net-buttons-bs/js/buttons.bootstrap.js',
        BOWER_DIR + '/JsBarcode/dist/barcodes/JsBarcode.code128.min.js',
        ASSETS_DIR + '/js/parsley-comparison.js',
        ASSETS_DIR + '/js/bootstrap-session-timeout.js',
        ASSETS_DIR + '/js/app.js',
        ASSETS_DIR + '/js/views/*'
    ],
    'css': [
        BOWER_DIR + '/bootstrap/dist/css/bootstrap.min.css',
        BOWER_DIR + '/font-awesome/css/font-awesome.min.css',
        BOWER_DIR + '/eonasdan-bootstrap-datetimepicker/build/css/bootstrap-datetimepicker.min.css',
        BOWER_DIR + '/datatables.net-bs/css/dataTables.bootstrap.css',
        BOWER_DIR + '/datatables.net-responsive-bs/css/responsive.bootstrap.css',
        BOWER_DIR + '/datatables.net-buttons-bs/css/buttons.bootstrap.css'
    ],
    'csslocal': [
        ASSETS_DIR + '/css/app.css'
    ],
    'fonts': [
        BOWER_DIR + '/bootstrap/dist/fonts/*',
        BOWER_DIR + '/font-awesome/fonts/*'
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

gulp.task('compile', [
    'compile-js',
    'compile-css',
    'compile-fonts'
]);

// re-compile when files change
gulp.task('watch', function() {
    gulp.watch(ASSETS.js, ['compile-js']);
    gulp.watch(ASSETS.csslocal, ['compile-css']);
});

// default task is to compile everything and then start watching
gulp.task('default', [
    'compile',
    'watch'
]);
