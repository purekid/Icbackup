Icbackup
========

PHP Increasement Backup 目录增量打包备份


###Usage


###Config  配置文件 (sample.json)
```json
{
    "log" : "/var/www/backup/backup.log",

    "tasks":[
        {
            "enable" : true,
            "multiThread" : false,
            "onlySaveHistory": true,
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

```
   $ ./bin/icbackup sample/config.json
```

待备份的目录a及其结构
```
--a
  └--b
  └--c
  ```
  
 1. 假设首次运行脚本时间为2014年10月1日，生成history并打包整个a目录，并生成a-2014-10-1-0-0.zip，因为第一次运行，目录是全增量，结构与初始目录完全相同：
```
--a
  └--b
  └--c
  ```

 2. 2014年10月1日-2日之间，目录中有多了几个文件，结构如下   
```
--a
  └--b
     └--d
  └--c
  └--e
```

 3. 此时于3日凌晨再次执行脚本，会扫描相对于上次执行脚本发生的目录变化，生成增量ZIP包，a-2014-10-3-0-0.zip内文件结构如下：
 
```
--a
  └--b
     └--d
  └--e
```

 4. 若config中配置有SCP项，打包后会自动SCP到远端目录保存

 5. 支持多线程，需要PHP支持pthreads扩展，同时讲配置中 'multiThread'设为true,'threadCount'设为使用的线程数量即可
