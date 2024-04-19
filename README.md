# BTSpider

## 简介

实现了 [BEP 05](http://www.bittorrent.org/beps/bep_0005.html)、[BEP 09](http://www.bittorrent.org/beps/bep_0009.html) 协议。

## 启动程序

下载好的 数据文件保存在 `data_file` 路径下面，数据解析按行读取，数据格式为 JSON 格式。

```bash
$ php btpsider server start -d

         ____ ___________       _     __
        / __ )_  __/ ___/____  (_)___/ /__  _____
       / __  |/ /  \__ \/ __ \/ / __  / _ \/ ___/
      / /_/ // /  ___/ / /_/ / / /_/ /  __/ /
     /_____//_/  /____/ .___/_/\__,_/\___/_/
                     /_/
 ----------------- ------------------------------------------------------------- 
  name              BTSpider
  data_file         /home/admin/workerspace/bt-spider/storage/data/btspider.txt
  stats_file        /home/admin/workerspace/bt-spider/storage/logs/_btstats.log
  log_file          /home/admin/workerspace/bt-spider/storage/logs/btspider.log
  swoole_host       0.0.0.0
  swoole_ports      6882
  swoole_log_file   /home/admin/workerspace/bt-spider/storage/logs/swoole.log    
  worker_num        4
  dispatch_mode     2
  reload_async      1
  log_level         2
  pid_file          /home/admin/workerspace/bt-spider/storage/tmp/swoole.pid
 ----------------- -------------------------------------------------------------
```

## 查看状态

```bash
$ php btpsider server status

 --------- ----------- ------------------- ------------------- -------------- ----------------- --------------------- 
                                                    Monitor Board
 --------- ----------- ------------------- ------------------- -------------- ----------------- --------------------- 
  Swoole    MasterPid   ManagerPid          WrokerId            WrokerPid      RequestCount      StartTime
            1632        1633                -1                  0              0                 2022-03-24 10:43:42  
 --------- ----------- ------------------- ------------------- -------------- ----------------- --------------------- 
  Process   Pid         ProcessName         ProcessGroup        MemoryUsage    MemoryPeakUsage   StartTime
            1638        BTSpider.Worker.0   BTSpider.Worker     4.52MB         6.00MB            2022-03-24 10:43:42  
            1639        BTSpider.Worker.1   BTSpider.Worker     4.52MB         6.00MB            2022-03-24 10:43:42  
            1640        BTSpider.Worker.2   BTSpider.Worker     4.52MB         6.00MB            2022-03-24 10:43:42  
            1641        BTSpider.Worker.3   BTSpider.Worker     4.52MB         6.00MB            2022-03-24 10:43:42  
 --------- ----------- ------------------- ------------------- -------------- ----------------- --------------------- 
  Worker    Pid         Waiting             Running             Success        Failure           Exceed
            1638        0                   0                   0              0                 0
            1639        0                   0                   0              0                 0
            1640        0                   0                   0              0                 0
            1641        0                   0                   0              0                 0
 --------- ----------- ------------------- ------------------- -------------- ----------------- --------------------- 
  Task      Pid         BootstrapTask       FetchMetadataTask   FindNodeTask   GetPeersTask      ResponseTask
            1638        0                   0                   0              0                 0
            1639        0                   0                   0              0                 0
            1640        0                   0                   0              0                 0
            1641        0                   0                   0              0                 0
 --------- ----------- ------------------- ------------------- -------------- ----------------- ---------------------
```

## 重载程序

```bash
$ php btspider server reload
```

## 停止运行

```bash
$ php btspider server stop
```

## 备注

如果需要修改配置，直接复制 `src/config.php` 文件到项目根目录即可。

## 其他

https://sunyunqiang.com/blog/bittorrent_protocol/#133-bittorrent-tracker

https://blog.csdn.net/spark_fountain/article/details/90635073
