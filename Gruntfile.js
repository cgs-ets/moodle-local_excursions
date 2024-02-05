// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
/* jshint node: true, browser: false */
/* eslint-env node */

/**
 * @package   local_excursions
 * @copyright 2021 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Grunt configuration
 */

module.exports = function(grunt) {
    var path = require('path')
        cwd = process.env.PWD || process.cwd();

    // Globbing pattern for matching all AMD JS source files.
    //var amdSrc = ['amd/src/*.js' : '**/amd/src/*.js'];

    /**
     * Function to generate the destination for the uglify task
     * (e.g. build/file.min.js). This function will be passed to
     * the rename property of files array when building dynamically:
     * http://gruntjs.com/configuring-tasks#building-the-files-object-dynamically
     *
     * @param {String} dst the current destination
     * @param {String} src the  matched src path
     * @return {String} The rewritten destination path.
     */
    var uglifyRename = function (dst, src) {
        dst = src.replace('src', 'build');
        dst = dst.replace('.js', '.min.js');
        dst = path.resolve(cwd, dst);
        return dst;
    };

    // Project configuration.
    grunt.initConfig({
        uglify: {
            amd: {
                files: [{
                    expand: true,
                    src: 'amd/src/*.js',
                    dest: '',
                    cwd: '.',
                    rename: function (dst, src) {
                      // To keep the source js files and make new files as `*.min.js`:
                      dst = src.replace('src', 'build');
                      dst = dst.replace('.js', '.min.js');
                      console.log(dst)
                      return dst;
                    }
                }],
                options: {report: 'min'}
            }
        },
        less: {
            development: {
                options: {
                    compress: false // We must not compress to keep the comments.
                },
                files: {
                    "excursions.css": "less/main.less",
                }
            }
       },
        watch: {
            styles: {
                files: ['less/*.less'], // which files to watch
                tasks: ['less'],
                options: {
                  nospawn: true
                }
            },
            amd: {
                files: ['**/amd/src/**/*.js'],
                tasks: ['uglify']
            }
        }
    });

    // Register NPM tasks.
    grunt.loadNpmTasks('grunt-contrib-uglify');
    grunt.loadNpmTasks('grunt-contrib-less');
    grunt.loadNpmTasks('grunt-contrib-watch');
   

    // Register CSS tasks.
    grunt.registerTask('default', ['less', 'uglify', 'watch']);

};
