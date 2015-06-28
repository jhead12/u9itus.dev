var gulp = require('gulp'),
     autoprefixer = require('gulp-autoprefixer'),
    gutil = require('gulp-util'),
    browserify = require('gulp-browserify'),
    compass = require('gulp-compass'),
    connect = require('gulp-connect'),
    gulpif = require('gulp-if'),
    uglify = require('gulp-uglify'),
    imagemin = require('gulp-imagemin'),
    pngquant = require('imagemin-pngquant'),


    concat = require('gulp-concat'),
    path = require('path');

    var notify = require("gulp-notify");
    minifyCSS = require('gulp-minify-css');
    less = require('gulp-less');
   


var env,

    jsSources,

    sound,
    custom,
    initInput,
    foundation,
    cookie,
    angular,
    bowerSources,
    sassSources,
    htmlSources,
    outputDir,
    init,
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
sassSources = ['app/assets/sass/style.scss'];
angular = ['app/assets/javascripts/angular/app.js'];
cookie = ['app/assets/javascripts/jquery.cookie.js'];
init = ['app/assets/javascripts/init.js'];


gulp.task('move', function () {
    return gulp.src('./app/assets/images/**/*.*')
        .pipe(imagemin({
            progressive: true,
            optimizationLevel:5,
            svgoPlugins: [{removeViewBox: false}],
            use: [pngquant()]
        }))
        .pipe(gulpif(env === 'production', gulp.dest(outputDir+'images')))
        .pipe(notify("Images: Moved and Compressed"))
});

// Copy images to production
//gulp.task('move', function() {
//    gulp.src('./app/assets/images/**/*.*')
//        .pipe(gulpif(env === 'production', gulp.dest(outputDir+'images')))
//        .pipe(notify("Images: Moved Images"))
//});




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

gulp.task('init', function() {
    gulp.src(init)
        .pipe(concat('init.js'))
        .pipe(browserify())
        .on('error', gutil.log)
        .pipe(gulpif(env === 'production', uglify()))
        .pipe(gulp.dest(outputDir + 'js'))
        .pipe(notify("Complete: Init Updated"))
        .pipe(connect.reload())
});

gulp.task('cookie', function() {
    gulp.src(jsSources)
        .pipe(concat('cookie.min.js'))
        .pipe(browserify())
        .on('error', gutil.log)
        .pipe(gulpif(env === 'production', uglify()))
        .pipe(gulp.dest(outputDir + 'js'))
        .pipe(notify("Complete: Javascripts Updated"))
        .pipe(connect.reload())
});


gulp.task('angular', function() {
    gulp.src(jsSources)
        .pipe(concat('app.js'))
        .pipe(browserify())
        .on('error', gutil.log)
        .pipe(gulpif(env === 'production', uglify()))
        .pipe(gulp.dest(outputDir + 'js'))
        .pipe(notify("Complete: Angular Updated"))
        .pipe(connect.reload())
});

gulp.task('default', function () {
     gulp.src('./public/css/*.css')
        .pipe(autoprefixer({
            browsers: ['last 2 versions'],
            cascade: false,
            expand: true,
             flatten: true,
            dest: ''
        }))
         .pipe(notify("Complete: Autoprefix"))

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


gulp.task('watch', function() {
  gulp.watch(jsSources, ['js']);
  gulp.watch(['./app/assets/sass/**/*.scss'], ['compass']);
    gulp.watch(['./app/assets/javascripts/angular/app.js']);

    gulp.watch(['./app/assets/images/**/*.*'],['move']);
    gulp.watch(['./app/assets/javascripts/intlTelInput.js'],['js2']);
    gulp.watch(['./app/assets/javascripts/init.js'],['init']);
    gulp.watch(['./app/assets/javascripts/sound/*.js'],['sound']);
    gulp.watch('./app/assets.javascripts/foundation/*.js',['foundation']);
    //gulp.watch(['./public/css/*.css'],['default']);

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




gulp.task('default', ['watch', 'js','sound','init','custom','compass','type','angular','foundation','cookie', 'move', 'connect']);
