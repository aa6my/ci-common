<?php
namespace Projek\CI\Common\Console;

use Projek\CI\Console\Cli;
use Projek\CI\Console\Commands;
use Projek\CI\Console\Arguments\Manager;

class Install extends Commands
{
    protected $name = 'install';
    protected $description = 'lang:console_install_desc';

    /**
     * {inheridoc}
     */
    public function register(Manager $arguments)
    {
        $arguments->add([
            'help' => [
                'prefix' => 'h',
                'longPrefix' => 'help',
                'description' => Cli::lang('console_display_help'),
                'noValue' => true
            ]
        ]);
    }

    /**
     * {inheridoc}
     */
    public function execute(Cli $console, Manager $arguments = null)
    {
        if (getenv('DYNO')) {
            $console->out(Cli::lang('console_install_heroku_env'));
            return $this->setup_heroku($console);
        }

        if (!$console->hasSttyAvailable()) {
            $console->out(Cli::lang('console_install_interactive'));
            $console->out(Cli::lang('console_install_manualy'));
            $console->br();
            return false;
        }

        if ($this->setup_config($console) === true) {
            $console->out('<bold><underline>'.Cli::lang('console_install_env_ready').'</underline></bold>');
            // $this->CI->load->library('migration');
            $server = $console->radio(
                Cli::lang('console_install_setup_server'),
                ['apache', 'nginx']
            ) ?: 'apache';

            call_user_func([$this, 'setup_'.$server], $console);
        }

        return $console->out('<green>'.Cli::lang('console_install_done').'</green>');
    }

    /**
     * Setup Procfile for heroku environment
     *
     * @param  Projek\CI\Console\Cli $console CLI instance
     * @return Projek\CI\Console\Cli
     */
    protected function setup_config(Cli $console)
    {
        if (file_exists(APPPATH.'.env')) {
            $console->out(Cli::lang('console_install_env_already'));
            return true;
        }

        $console->out('<bold><underline>'.Cli::lang('console_install_setup_intro').'</underline></bold>');

        $base_url = $console->input(Cli::lang('console_install_setup_appurl'), '/');
        $db_host  = $console->input(Cli::lang('console_install_setup_dbhost'), 'localhost');
        $db_user  = $console->input(Cli::lang('console_install_setup_dbuser'), 'root');
        $db_pass  = $console->password(Cli::lang('console_install_setup_dbpass'));
        $db_name  = $console->input(Cli::lang('console_install_setup_dbname'));
        $db_pref  = $console->input(Cli::lang('console_install_setup_dbpref'), 'app_');

        $replacement = [
            'APP_BASE_URL=\'/\'' => 'APP_BASE_URL=\''.$base_url.'\'',
            'APP_DB_HOST='       => 'APP_DB_HOST='.$db_host,
            'APP_DB_USER='       => 'APP_DB_USER='.$db_user,
            'APP_DB_PASS='       => 'APP_DB_PASS='.$db_pass,
            'APP_DB_NAME='       => 'APP_DB_NAME='.$db_name,
            'APP_DB_PREF='       => 'APP_DB_PREF='.$db_pref,
        ];

        $key = substr(md5(uniqid(mt_rand().serialize($replacement))), 0, 16);
        $replacement['APP_PRIVATE_KEY='] = 'APP_PRIVATE_KEY='.md5($key);

        copy(APPPATH.'env.txt', APPPATH.'.env');

        $file = APPPATH.'.env';
        $content = file_get_contents($file);
        $content = str_replace(array_keys($replacement), array_values($replacement), $content);
        file_put_contents($file, $content);

        return true;
    }

    /**
     * Setup Procfile for heroku environment
     *
     * @param  Projek\CI\Console\Cli $console CLI instance
     * @return Projek\CI\Console\Cli
     */
    protected function setup_heroku(Cli $console)
    {
        if (file_exists(FCPATH.'Procfile')) {
            return $console->out(Cli::lang('console_install_heroku_already'));
        }

        $file = fopen(FCPATH.'Procfile', 'w');
        $content = 'web: vendor/bin/heroku-php-nginx public/';
        fwrite($file, $content);
        fclose($file);

        return $console->out(Cli::lang('console_install_heroku_ready'));
    }

    /**
     * Setup Apache .htaccess
     *
     * @param  Projek\CI\Console\Cli $console     CLI instance
     * @param  string                $rewriteBase Apache rewrite base
     * @return Projek\CI\Console\Cli
     */
    protected function setup_apache(Cli $console, $rewriteBase = '/')
    {
        if (file_exists(FCPATH.'public/.htaccess')) {
            return $console->out(Cli::lang('console_install_apache_already'));
        }

        $content = <<<HTACCESS
<IfModule mod_rewrite.c>
    Options +FollowSymLinks -Indexes
    RewriteEngine on

    RewriteBase $rewriteBase

    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php?/$1 [L]
</IfModule>

<IfModule !mod_rewrite.c>
    ErrorDocument 404 index.php
</IfModule>
HTACCESS;

        $file = fopen(FCPATH.'public/.htaccess', 'w');
        fwrite($file, $content);
        fclose($file);

        return $console->out(Cli::lang('console_install_apache_ready'));
    }

    /**
     * Setup for NginX
     *
     * @param  Projek\CI\Console\Cli $console CLI instance
     * @return Projek\CI\Console\Cli
     */
    protected function setup_nginx(Cli $console)
    {
        return $console->out(Cli::lang('console_install_nginx_ready'));
    }
}
