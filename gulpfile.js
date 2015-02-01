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

    sound,
    custom,
    initInput,
    foundation,
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
foundation = ['app/assets/javascripts/foundation/foundation.js','app/assets/javascripts/foundation/foundation.reveal.js'];
jsSources = ['app/assets/javascripts/bootstrap.js'];
initInput = ['app/assets/javascripts/intlTelInput.js'];
sound = ['app/assets/javascripts/sound/soundmanager2.js'];
custom = ['app/assets/javascripts/sound/custom.js'];
sassSources = ['app/assets/sass/main.scss'];



gulp.task('js', function() {
  gulp.src(jsSources)
    .pipe(concat('foundation.min.js'))
    .pipe(browserify())
    .on('error', gutil.log)
    .pipe(gulpif(env === 'production', uglify()))
    .pipe(gulp.dest(outputDir + 'js'))
    .pipe(notify("Complete: Javascripts Updated"))
    .pipe(connect.reload())
});


gulp.task('foundation', function() {
    gulp.src(foundation)
        .pipe(concat('script.js'))
        .pipe(browserify())
        .on('error', gutil.log)
        .pipe(gulpif(env === 'production', uglify()))
        .pipe(gulp.dest(outputDir + 'js'))
        .pipe(notify("Complete: Foundation Updated"))
        .pipe(connect.reload())
});
gulp.task('sound', function() {
    gulp.src(sound)
        .pipe(concat('audio.js'))
        .pipe(browserify())
        .on('error', gutil.log)
        .pipe(gulpif(env === 'production', uglify()))
        .pipe(gulp.dest(outputDir + 'js'))
        .pipe(notify("Complete: Audio Updated"))
        .pipe(connect.reload())
});
gulp.task('type', function() {
    gulp.src(initInput)
        .pipe(concat('type.js'))
        .pipe(browserify())
        .on('error', gutil.log)
        .pipe(gulpif(env === 'production', uglify()))
        .pipe(gulp.dest(outputDir + 'js'))
        .pipe(notify("Complete: Type Updated"))
        .pipe(connect.reload())
});
gulp.task('custom', function() {
    gulp.src(custom)
        .pipe(concat('custom.js'))
        .pipe(browserify())
        .on('error', gutil.log)
        .pipe(gulpif(env === 'production', uglify()))
        .pipe(gulp.dest(outputDir + 'js'))
        .pipe(notify("Complete: Custom Updated"))
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
    gulp.watch(['./app/assets/javascripts/intlTelInput.js'],['js2']);
    gulp.watch(['./app/assets/javascripts/sound/*.js'],['sound']);
    gulp.watch('./app/assets.javascripts/foundation/*.js',['foundation']);
    //gulp.watch(['./app/assets/javascripts/sound/custom.js'],['custom']);
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




gulp.task('default', ['watch', 'js','sound','custom', 'compass','foundation','type', 'move', 'connect']);
