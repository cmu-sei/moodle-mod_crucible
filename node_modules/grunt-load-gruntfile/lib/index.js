module.exports = function(grunt) {


	grunt.loadGruntfile = function(p) {
		var path = require("path");
		var dir;
		var originalDir = process.cwd();

		grunt.projectPath = originalDir;
		if (p.indexOf("Gruntfile") == -1) {
			dir = p;
		} else {
			dir = path.dirname(p);
		}

		process.chdir(dir);
		require(process.cwd() + "/Gruntfile.js")(grunt);
		process.chdir(originalDir);
	};

};