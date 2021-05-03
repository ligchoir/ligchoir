'use strict';

// credit: https://github.com/j-hannah/atlas/blob/master/gulpfile.js
// https://github.com/Minds/minds/issues/95#issuecomment-424755248

var gulp = require('gulp');
var watch = require('gulp-watch');
//var imagemin = require('gulp-imagemin');
var newer = require('gulp-newer');
var concat = require('gulp-concat');
var rename = require('gulp-rename');
//var svgmin = require('gulp-svgmin');
var sass = require('gulp-sass');
var cleanCSS = require('gulp-clean-css'); // replaces var cssmin = require('gulp-minify-css');
var sourcemaps = require('gulp-sourcemaps');
var autoprefixer = require('gulp-autoprefixer');
//var runSequence = require('run-sequence');

// after upgrading to gulp 4
const runSequence = require('gulp4-run-sequence');
runSequence.options.errorOnInvalidArgumentType = true;

// paths
//var imgSrc = './images/src/*';
//var imgDest = './images';
//var svgSrc = './images/svg-src/*';
//var svgDest = './images';
var sassSrc = './scss/**/*.scss';
var sassDest = './css';

gulp.task('sass', gulp.series(function(done) {
gulp.src(sassSrc)
  .pipe(sourcemaps.init())
  .pipe(sass({
    errLogToConsole: true
    }))
  .pipe(autoprefixer('last 2 version'))
  .pipe(sourcemaps.write())
  .pipe(gulp.dest(sassDest));
done();
}));

gulp.task('cssmin', gulp.series(function(done) {
  return gulp.src('./css/*.css')
    .pipe(cleanCSS({compatibility: 'ie8'}))
    .pipe(gulp.dest(sassDest));
done();
}));

/*
// add image minify task
gulp.task('imagemin', function() {
  return gulp.src(imgSrc)
    .pipe(newer(imgSrc))
    .pipe(imagemin())
    .pipe(gulp.dest(imgDest));
});
*/

/*
// add svg minify task
gulp.task('svgmin', function() {
  return gulp.src(svgSrc)
    .pipe(newer(svgSrc))
    .pipe(svgmin())
    .pipe(gulp.dest(svgDest));
});
*/

// Run tasks without watching.
gulp.task('build', function(done) {
  //runSequence('sass', 'imagemin', 'svgmin', 'cssmin', callback);
  gulp.series('sass', 'cssmin');
  done();
});

// Rerun the task when a file changes
gulp.task('watch', function() {
  gulp.watch(sassSrc, gulp.series('sass'));
  gulp.watch('./css/styles.css', gulp.series('cssmin'));
  //gulp.watch(imgSrc, ['imagemin']);
  //gulp.watch(svgSrc, ['svgmin']);
});

gulp.task('default', function(done) {
  // runSequence('sass', 'imagemin', 'svgmin', 'cssmin', 'watch', callback);
  gulp.series('sass', 'cssmin', 'watch');
  done();
});
