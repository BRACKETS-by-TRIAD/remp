<?php

$app = 'Sso';
$whitelist = [$app];

use function Deployer\{has, host, task, run, set, get, add, before, after, localhost, input};

require dirname(__FILE__) . '/vendor/autoload.php';
require dirname(__FILE__) . '/vendor/deployer/deployer/recipe/common.php';
require dirname(__FILE__) . '/vendor/deployphp/recipes/recipe/slack.php';
require dirname(__FILE__) . '/vendor/deployphp/recipes/recipe/rabbit.php';

set('repository', 'git@gitlab.com:remp/remp.git');
set('keep_releases', 4);
set('shared_dirs', ['storage/app', 'storage/logs']);
set('shared_files', ['.env']);

localhost('remp2020')
    ->set('deploy_path', '/data/web/remp2020.com/app/Sso')
    ->set('branch', 'master')
    ->stage('sso');

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

task('deploy:migration', function() {
    run("cd {{release_path}}; php artisan migrate --force");
})->desc('Migrate database');

task('deploy:tmplink', function() {
    run("rm -fr {{release_path}}/storage/framework/cache");
    run("ln -s /tmp/remp_sso_cache {{release_path}}/storage/framework/cache");

    run("rm -fr {{release_path}}/storage/framework/sessions");
    run("ln -s /tmp/remp_sso_sessions {{release_path}}/storage/framework/sessions");

    run("rm -fr {{release_path}}/storage/framework/testing");
    run("ln -s /tmp/remp_sso_testing {{release_path}}/storage/framework/testing");

    run("rm -fr {{release_path}}/storage/framework/views");
    run("ln -s /tmp/remp_sso_views {{release_path}}/storage/framework/views");
})->desc('Temp symlink');

task('deploy', [
    'deploy:prepare',
    'deploy:release',
    'deploy:extract_project',
    'deploy:shared',
    'deploy:tmplink',
    'deploy:migration',
    'deploy:symlink',
    'cleanup',
])->desc('Deploy your project');
