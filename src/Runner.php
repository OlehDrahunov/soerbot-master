<?php

namespace SoerBot;

use React\EventLoop\Factory;
use CharlotteDunois\Livia\LiviaClient;
use SoerBot\Database\Settings\CapsuleSetup;

class Runner
{
    /**
     * @var Factory
     */
    private $loop;

    /**
     * @var LiviaClient
     */
    private $client;

    /**
     * Runner constructor.
     *
     * @param null $client
     * @param null $loop
     * @throws Exceptions\ConfigurationFileNotFound
     */
    public function __construct($client = null, $loop = null)
    {
        $this->loop = $loop ?? $this->makeLoop();
        $this->client = $client ?? $this->makeClient();

        CapsuleSetup::setup();
    }

    public function execute()
    {
        if ($this->config('debug', false)) {
            $this->client->on('debug', function ($message) {
                echo $message . "\n";
            });
        }

        $this->settings();
        $this->logReadyState();
        $this->login();
        $this->greeting();
        $this->registerExitEvent();
        $this->runningLoop();
    }

    /**
     * @return \React\EventLoop\LoopInterface
     */
    private function makeLoop()
    {
        return $this->loop = Factory::create();
    }

    /**
     * @return void
     */
    public function settings(): void
    {
        // Не регистрируем дефолтные команды, поэтому не используем $this->client->registry->registerDefaults();
        $this->client->registry->registerDefaultTypes();
        $this->client->registry->registerDefaultGroups();

        $this->client->registry->registerGroup(
            (new \CharlotteDunois\Livia\Commands\CommandGroup($this->client, 'games', 'Games', true))
        );
        // Register the command group for our example command
        $this->client->registry->registerGroup(['id' => 'moderation', 'name' => 'Moderation']);

        // Register our commands (this is an example path)
        // TODO вынести регистрацию команд из файла в структуру.
        $this->client->registry->registerCommand(...$this->loadCommands());
    }

    /**
     * @return void
     */
    public function logReadyState(): void
    {
        $this->client->on('ready', function () {
            echo 'Logged in as ' . $this->client->user->tag . ' created on ' .
                $this->client->user->createdAt->format('d.m.Y H:i:s') . PHP_EOL;
        });
    }

    /**
     * @throws Exceptions\ConfigurationFileNotFound
     * @return void
     */
    public function login(): void
    {
        $this->client
            ->login($this->config('key'))
            ->done();
    }

    /**
     * @return void
     */
    private function registerExitEvent(): void
    {
        $this->client->once('stop', function () {
            echo 'stop';
            $this->loop->stop();
        });
    }

    /**
     * @throws \Exception error
     * @return void
     */
    public function greeting(): void
    {
        $this->client->once('ready', function () {
            try {
                $channel = $this->client->channels->first(function ($channel) {
                    return $channel->name === 'основной';
                });

                if ($channel && Configurator::get('development', false)) {
                    $channel->send('SoerBot started in development mode.')
                        ->done(null, function ($error) {
                            echo $error . PHP_EOL;
                        });
                }
            } catch (\Exception $error) {
            }
        });
    }

    /**
     * @return void
     */
    public function runningLoop(): void
    {
        $this->loop->run();
    }

    /**
     * @throws Exceptions\ConfigurationFileNotFound
     * @return LiviaClient
     */
    private function makeClient()
    {
        return new LiviaClient(
            $this->configurationForClient(),
            $this->loop
        );
    }

    /**
     * @throws Exceptions\ConfigurationFileNotFound
     * @return array
     */
    private function configurationForClient()
    {
        return [
            'owners' => $this->config('users'),
            'unknownCommandResponse' => false,
            'commandPrefix' => $this->config('command-prefix'),
        ];
    }

    /**
     * @param $key
     * @param null $default
     * @throws Exceptions\ConfigurationFileNotFound
     * @return mixed|null
     */
    private function config($key, $default = null)
    {
        return Configurator::get($key, $default);
    }

    /**
     * @return string[]
     */
    private function loadCommands()
    {
        return \CharlotteDunois\Livia\Utils\FileHelpers::recursiveFileSearch(__DIR__ . '/../commands', '*.command.php');
    }
}
