<?php

use function Deployer\{has, host, task, run, set, get, add, before, after, localhost, input};

require dirname(__FILE__) . '/vendor/autoload.php';
require dirname(__FILE__) . '/vendor/deployer/deployer/recipe/common.php';
require dirname(__FILE__) . '/vendor/deployer/recipes/recipe/slack.php';
require dirname(__FILE__) . '/vendor/deployer/recipes/recipe/rabbit.php';

set('repository', 'git@gitlab.com:remp/remp.git');
set('keep_releases', 4);
set('shared_dirs', ['log']);
set('shared_files', ['.env', 'app/config/config.local.neon']);
set('default_timeout', 900);
set('rabbit', [
    'host' => getenv('DEPLOY_RABBIT_HOST'),
    'port' => getenv('DEPLOY_RABBIT_PORT'),
    'username' => getenv('DEPLOY_RABBIT_USERNAME'),
    'password' => getenv('DEPLOY_RABBIT_PASSWORD'),
    'channel' => getenv('DEPLOY_RABBIT_CHANNEL'),
    'message' => 'remp',
    'vhost'  => getenv('DEPLOY_RABBIT_VHOST')
]);

localhost('remp2020')
    ->set('deploy_path', '/data/web/remp2020.com/app/Mailer')
    ->set('branch', 'master')
    ->stage('mailer');

task('deploy:vendors', function() {
    if (has('previous_release')) {
        run('cp -R {{previous_release}}/vendor {{release_path}}/vendor');
    }
    run('cd {{release_path}} && {{env_vars}} {{bin/composer}} {{composer_options}}');
});

task('deploy:extract_project', function() {
    run("cp -fr . {{release_path}}");
    run("cp -fr ../Composer {{release_path}}");
    run("sed -i -e 's/\.\.\/Composer/.\/Composer/g' {{release_path}}/composer.lock");
})->desc('Monorepo custom release, will migrate to subrepos');

task('deploy:tmplink', function() {
    run("rm -fr {{release_path}}/temp");
    run("ln -s /tmp/remp_mailer {{release_path}}/temp");
})->desc('Temp symlink');

task('deploy:migration', function() {
    run("cd {{release_path}}; php bin/command.php migrate:migrate");
})->desc('Migrate database');

task('deploy:restart_hermes', function() {
    run("touch {{deploy_path}}/shared/system/hermes_restart");
});

task('deploy', [
    'deploy:prepare',
    'deploy:release',
    'deploy:extract_project',
    'deploy:shared',
    'deploy:tmplink',
//    'deploy:migration', // migrations not enabled until running from shared CRM database
    'deploy:symlink',
    'deploy:restart_hermes',
    'deploy:rabbit',
    'cleanup',
])->desc('Deploy your project');
