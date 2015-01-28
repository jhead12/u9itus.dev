var gulp = require('gulp'),
    gutil = require('gulp-util'),
    browserify = require('gulp-browserify'),
    compass = require('gulp-compass'),
    connect = require('gulp-connect'),
    gulpif = require('gulp-if'),
    uglify = require('gulp-uglify'),
    minifyHTML = require('gulp-minify-html'),
    concat = require('gulp-concat');
    path = require('path');
	  autoprefixer = require('gulp-autoprefixer')
    var notify = require("gulp-notify");
    minifyCSS = require('gulp-minify-css');
    less = require('gulp-less');
   


var env,

    jsSources,
    bowerSources,
    sassSources,
    htmlSources,
    outputDir,
    sassStyle;

env = 'production';


if (env==='development') {
  outputDir = 'public/';
  sassStyle = 'expanded';
} else {

 outputDir = './public/';

  sassStyle = 'compressed';
}

jsSources = ['app/assets/javascripts/animator.js','app/assets/javascripts/bootstrap.js','app/assets/javascripts/intlTelInput.js','app/assets/javascripts/soundmanager2.js','app/assets/javascripts/audio_dials.js'];

sassSources = ['app/assets/sass/main.scss'];

gulp.task('js', function() {
  gulp.src(jsSources)
    .pipe(concat('script.js'))
    .pipe(browserify())
    .on('error', gutil.log)
    .pipe(gulpif(env === 'production', uglify()))
    .pipe(gulp.dest(outputDir + 'js'))
    .pipe(notify("Complete: Javascripts Updated"))
    .pipe(connect.reload())
});

gulp.task('compass', function() {
  gulp.src(sassSources)
    .pipe(compass({
      sass: 'app/assets/sass',
      css: outputDir + 'css',
      image: outputDir + 'images',
      style: sassStyle,

      require: ['susy', 'breakpoint']
    })

    .on('error', gutil.log))

   .pipe(gulp.dest( outputDir + 'css'))
    .pipe(notify("Complete: Sass Updated"))
    .pipe(connect.reload())

});

gulp.task('minify-css', function() {
  gulp.src('public/css/**.css')
    .pipe(minifyCSS({keepBreaks:true}))
    .pipe(gulp.dest('public/css/'))
});
// Copy images to production
gulp.task('move', function() {
    gulp.src('./app/assets/images/**/*.*')
        .pipe(gulpif(env === 'production', gulp.dest(outputDir+'images')))
        .pipe(notify("Images: Moved Images"))
});

gulp.task('watch', function() {
  gulp.watch(jsSources, ['js']);
  gulp.watch(['./app/assets/sass/**/*.scss'], ['compass']);
    gulp.watch(['./app/assets/images/**/*.*'],['move']);
 //gulp.watch('components/index.html', ['html']);
});

gulp.task('connect', function() {
  connect.server({
    root: outputDir,
    livereload: true
  });
});


// gulp.task('html', function() {
//   gulp.src('components/index.html')
//    .pipe(gulpif(env === 'production', minifyHTML()))     
//    .pipe(gulpif(env === 'production', gulp.dest(outputDir)))
//     .pipe(connect.reload())
//      });




gulp.task('default', ['watch', 'js', 'compass', 'move', 'connect']);
