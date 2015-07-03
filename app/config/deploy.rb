set :application, "set your application name here"
set :domain,      "orukusaki.co.uk"
set :deploy_to,   "/var/www/slackbot"
set :app_path,    "app"

set :repository,  "git@github.com:orukusaki/SlackBot.git"
set :scm,         :git

role :web,        domain                         # Your HTTP server, Apache/etc
role :app,        domain, :primary => true       # This may be the same as your `Web` server

set  :keep_releases,  3

set :shared_files,        ["app/config/parameters.yml"]
set :shared_children,     [app_path + "/logs", web_path + "/uploads", "vendor"]
set :use_sudo,            false

set :webserver_user,      "www-data"
set :permission_method,   :acl
set :use_set_permissions, true
set :user,                "codeship"

before "symfony:cache:warmup", "symfony:composer:install"

# Be more verbose by uncommenting the following line
logger.level = Logger::MAX_LEVEL
