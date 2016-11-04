var ASSETS_DIR = './web/assets';
var BOWER_DIR = './bower_components';
var ASSETS = {
    'js': [
        BOWER_DIR + '/jquery/dist/jquery.min.js',
        BOWER_DIR + '/moment/min/moment.min.js',
        BOWER_DIR + '/bootstrap/dist/js/bootstrap.min.js',
        BOWER_DIR + '/underscore/underscore-min.js',
        BOWER_DIR + '/backbone/backbone-min.js',
        BOWER_DIR + '/eonasdan-bootstrap-datetimepicker/build/js/bootstrap-datetimepicker.min.js',
        BOWER_DIR + '/parsleyjs/dist/parsley.min.js',
        ASSETS_DIR + '/js/parsley-comparison.js',
        ASSETS_DIR + '/js/bootstrap-session-timeout.js',
        ASSETS_DIR + '/js/app.js',
        ASSETS_DIR + '/js/views/*'
    ],
    'css': [
        BOWER_DIR + '/bootstrap/dist/css/bootstrap.min.css',
        BOWER_DIR + '/font-awesome/css/font-awesome.min.css',
        BOWER_DIR + '/eonasdan-bootstrap-datetimepicker/build/css/bootstrap-datetimepicker.min.css'
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
