Icbackup
========

PHP Increasement Backup 目录增量打包备份

###Features 功能点
- 可配置
- 增量备份
- 自动ZIP打包
- 多线程支持
- 支持多远端SCP同步

###Usage 用法
 
 ```
    $ ./bin/icbackup sample/config.json
 ```

###Config  配置文件 (sample.json)
```json
{
    "log" : "/var/www/backup/backup.log",

    "tasks":[
        {
            "enable" : true,
            "multiThread" : false,
            "onlySaveHistory": false,
            "threadCount" : 1,
            "ignoreZipTimestampBefore": 1412995946, 
            "name" : "client",
            "dir" : "/var/www/files/a",
            "storage" : "/var/www/backup",
            "scp":[
                {
                "host" : "192.168.1.2",
                "port" : "22",
                "user" : "michael",
                "path" : "/home/michael/backup",
                "password" : "123456"
                },
                {
                    "host" : "192.168.1.3",
                    "port" : "22",
                    "user" : "michael",
                    "path" : "/home/michael/backup",
                    "password" : "123456"
                }
            ],
            "ignoreUnmodifiedDir":[
                "formQR",
                "image",
                "contactQR",
                "html"
           ]

       }
   ]
}
```


待备份的目录a及其结构
```
--a
  └--b
  └--c
  ```
  
 1.假设首次运行脚本时间为2014年10月1日，生成history并打包整个a目录，并生成a-2014-10-1-0-0.zip，因为第一次运行，目录是全增量，结构与初始目录完全相同：
```
--a
  └--b
  └--c
  ```

 2.2014年10月1日-2日之间，目录中有多了几个文件，结构如下   
```
--a
  └--b
     └--d
  └--c
  └--e
```

 3.此时于3日凌晨再次执行脚本，会扫描相对于上次执行脚本发生的目录变化，生成增量ZIP包，a-2014-10-3-0-0.zip内文件结构如下：
 
```
--a
  └--b
     └--d
  └--e
```

 4.若config中配置有SCP项，打包后会自动SCP到远端目录保存

 5.支持多线程，需要PHP支持pthreads扩展，同时讲配置中 'multiThread'设为true,'threadCount'设为使用的线程数量即可
 
 6.配置中 'ignoreUnmodifiedDir' 是很重要的一项，对性能提升很关键，举个栗子：
 
 对于一般的程序类项目，附件目录的结构一般是一致的，譬如最后一级目录存放图片的目录都名为images，存放文档的都名为docs
 
 ```
 --a
  └--b
     └--images
     └--docs
  └--c
     └--images
     └--docs
  └--e
     └--images
     └--docs
```
 
 此时你可以如此配置:            "ignoreUnmodifiedDir" : ["images","docs"] ,这样的作用就是当发现名为images或docs的目录本身未发生变化(文件夹的ftime时间)，不会进入目录里去递归扫描，对于性能会有很大提升。
