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
var runSequence = require('run-sequence');

// paths
//var imgSrc = './images/src/*';
//var imgDest = './images';
//var svgSrc = './images/svg-src/*';
//var svgDest = './images';
var sassSrc = './scss/**/*.scss';
var sassDest = './css';

gulp.task('sass', function () {
gulp.src(sassSrc)
  .pipe(sourcemaps.init())
  .pipe(sass({
    errLogToConsole: true
    }))
  .pipe(autoprefixer('last 2 version'))
  .pipe(sourcemaps.write())
  .pipe(gulp.dest(sassDest));
});

gulp.task('cssmin', function() {
  return gulp.src('./css/*.css')
    .pipe(cleanCSS({compatibility: 'ie8'}))
    .pipe(gulp.dest(sassDest));
});

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
gulp.task('build', function(callback) {
  //runSequence('sass', 'imagemin', 'svgmin', 'cssmin', callback);
  runSequence('sass', 'cssmin', callback);
});

// Rerun the task when a file changes
gulp.task('watch', function() {
  gulp.watch(sassSrc, ['sass']);
  gulp.watch('./css/styles.css', ['cssmin']);
  //gulp.watch(imgSrc, ['imagemin']);
  //gulp.watch(svgSrc, ['svgmin']);
});

gulp.task('default', function(callback) {
  // runSequence('sass', 'imagemin', 'svgmin', 'cssmin', 'watch', callback);
  runSequence('sass', 'cssmin', 'watch', callback);
});