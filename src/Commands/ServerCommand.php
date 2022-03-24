<?php

namespace BTSpider\Commands;

use BTSpider\Support\Utils;
use InvalidArgumentException;
use Illuminate\Container\Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ServerCommand extends Command
{
    protected $app;

    protected static $defaultName = 'server';

    protected static $supportActions = ['start', 'restart', 'reload', 'stop', 'pause', 'status'];

    public function __construct(Container $app)
    {
        $this->app = $app;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription($this->app['config']->get('name') . ' 管理器');
        $this->addArgument('action', InputArgument::REQUIRED, '支持操作: ' . implode('|', self::$supportActions));
        $this->addOption('daemon', 'd', InputOption::VALUE_NONE, '使用守护进程运行');
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $action = $input->getArgument('action');
        if ($action && !in_array($action, self::$supportActions)) {
            throw new InvalidArgumentException('不支持该操作: ' . $action);
        }

        if ($input->hasOption('daemon') && $input->getOption('daemon')) {
            $this->app->get('config')->set('server.settings.daemonize', true);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $action = $input->getArgument('action');
        return $this->$action($input, $output);
    }

    private function start(InputInterface $input, OutputInterface $output)
    {
        if (is_file($pidFile = $this->app['config']->get('server.settings.pid_file'))) {
            $pid = @file_get_contents($pidFile);
            if ($pid) {
                if (!\Swoole\Process::kill($pid, 0)) {
                    @unlink($pidFile);
                } else {
                    $this->app['style']->error('PID：' . $pid . ' 已经运行，请勿重复操作。');
                    return Command::FAILURE;
                }
            }
        }

        $serverPorts = $this->app['config']->get('server.ports', []);
        array_unshift($serverPorts, $this->app['config']->get('server.port'));

        $row = [
            'name' => $this->app['config']->get('name'),
            'log_path' => $this->app['config']->get('log_path'),
            'swoole_host' => $this->app['config']->get('server.host'),
            'swoole_ports' => implode('|', $serverPorts),
            'swoole_log' => $this->app['config']->get('server.settings.log_file'),
            'worker_num' =>  $this->app['config']->get('worker.worker_num'),
        ] + $this->app['config']->get('server.settings', []);

        $this->app['style']->text('<info>' . Utils::logo() . '</info>');
        $this->app['style']->horizontalTable(array_keys($row), [array_values($row)]);

        $this->app['server']->getServer()->start();

        return Command::SUCCESS;
    }

    private function reload(InputInterface $input, OutputInterface $output)
    {
        $pidFile = $this->app['config']->get('server.settings.pid_file');
        if (!is_file($pidFile)) {
            $this->app['style']->error('PID 文件不存在，请检查程序是否运行在守护进程下。');
            return Command::FAILURE;
        }

        $pid = file_get_contents($pidFile);
        if (!\Swoole\Process::kill($pid, 0)) {
            $this->app['style']->warning('PID：' . $pid . ' 不存在');
            unlink($pidFile);
            return Command::FAILURE;
        }
        \Swoole\Process::kill($pid, SIGUSR1);
        $this->app['style']->success('PID：' . $pid . ' 发送重启命令成功。时间：' . date("Y-m-d H:i:s"));
        return Command::SUCCESS;
    }

    private function restart(InputInterface $input, OutputInterface $output)
    {
        $this->stop($input, $output);
        sleep(1);
        $this->start($input, $output);

        return Command::SUCCESS;
    }

    private function stop(InputInterface $input, OutputInterface $output)
    {
        $pidFile = $this->app['config']->get('server.settings.pid_file');
        if (!is_file($pidFile)) {
            $this->app['style']->error('PID 文件不存在，请检查程序是否运行在守护进程下。');
            return Command::FAILURE;
        }

        $pid = file_get_contents($pidFile);
        if (!\Swoole\Process::kill($pid, 0)) {
            $this->app['style']->warning('PID：' . $pid . ' 不存在');
            unlink($pidFile);
            return Command::FAILURE;
        }

        \Swoole\Process::kill($pid, SIGKILL);

        $time = time();
        while (true) {
            usleep(1000);
            if (!\Swoole\Process::kill($pid, 0)) {
                if (is_file($pidFile)) {
                    @unlink($pidFile);
                }
                $this->app['style']->success('PID：' . $pid . ' 发送停止命令成功。时间：' . date("Y-m-d H:i:s"));
                break;
            } else {
                if (time() - $time > 5) {
                    $this->app['style']->error('PID：' . $pid . ' 发送停止命令失败。请重试或者使用 --force 参数强制停止进程。');
                    break;
                }
            }
        }
        return Command::SUCCESS;
    }

    private function status(InputInterface $input, OutputInterface $output)
    {
        while (true) {
            $pidFile = $this->app['config']->get('server.settings.pid_file');
            if (!is_file($pidFile)) {
                $this->app['style']->error('PID 文件不存在，请检查程序是否运行在守护进程下。');
                return Command::FAILURE;
            }
            $statsFile = $this->app['config']->get('stats_file');
            if (!is_file($statsFile)) {
                $this->app['style']->error('STATS 文件不存在，请检查程序是否运行在守护进程下。');
                return Command::FAILURE;
            }
            $data = file_get_contents($statsFile);
            $output->write("\33[H\33[2J\33(B\33[m");
            $output->write($data);
            sleep(1);
        }

        return Command::SUCCESS;
    }
}
