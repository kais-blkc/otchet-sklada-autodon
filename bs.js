const { spawn } = require('child_process');
const browserSync = require('browser-sync').create();

//Start php server
const php = spawn('php', ['-S', 'localhost:8000', '-t', 'app'], {
  stdio: 'inherit',
});

// Start browser sync
browserSync.init({
  proxy: 'localhost:8000',
  files: ['**/*.php', '**/*.js', '**/*.css', '**/*.html'],
  ui: false,
  notify: false,
  logLevel: 'info',
  open: true,
});

// close processes on exit
process.on('exit', () => php.kill());
process.on('SIGINT', () => process.exit());
