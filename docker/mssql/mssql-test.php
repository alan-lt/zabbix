<?php

$database = 'master';
$user = 'zabbix';
$password = 'xxxxxxxxxxx';
$hostname = '10.0.0.57';
$server = 'servername001.full.domain.com';
$mssql_instance = 'servername001';
$port = '1433';
$db = odbc_connect("Driver={ODBC Driver 18 for SQL Server};HOSTNAME=$hostname;SERVER=$server;Database=$database;PORT=$port;PROTOCOL=TCPIP;Encrypt=no;TrustServerCertificate=yes", $user, $password);

$get_last_backup = "
SELECT bs.database_name as dbname,[type], DATEDIFF(SECOND, bs.backup_finish_date, getdate()) as timesincelastbackup, (DATEDIFF(SECOND, bs.backup_start_date, bs.backup_finish_date)) as duration
FROM msdb.dbo.backupset as bs WHERE bs.database_name not in (
SELECT
AGDatabases.database_name AS Databasename
FROM sys.dm_hadr_availability_group_states States
INNER JOIN master.sys.availability_groups Groups ON States.group_id = Groups.group_id
INNER JOIN sys.availability_databases_cluster AGDatabases ON Groups.group_id = AGDatabases.group_id
WHERE primary_replica != @@Servername OR primary_replica is NULL
)
GROUP BY bs.database_name, backup_finish_date, [type], backup_start_date
HAVING backup_finish_date = (SELECT MAX(backup_finish_date) from msdb.dbo.backupset WHERE database_name = bs.database_name AND bs.type = [type])
ORDER BY bs.database_name
";

$SQL_Exec_String = '
SELECT object_name,counter_name,instance_name,cntr_value
FROM sys.dm_os_performance_counters
UNION
SELECT \'{$MSSQL.INSTANCE}\' as object_name,\'Version\' as counter_name,@@version as instance_name,0 as cntr_value
UNION
SELECT \'{$MSSQL.INSTANCE}\' as object_name,\'Uptime\' as counter_name,\'\' as instance_name,DATEDIFF(second,sqlserver_start_time,GETDATE()) as cntr_value
FROM sys.dm_os_sys_info
UNION
SELECT \'{$MSSQL.INSTANCE}:Databases\' as object_name,\'State\' as counter_name,name as instance_name,state as cntr_value
FROM sys.databases
UNION
SELECT a.object_name,\'BufferCacheHitRatio\' as counter_name,\'\' as instance_name,cast(a.cntr_value*100.0/b.cntr_value as dec(3,0)) as cntr_value
FROM sys.dm_os_performance_counters a
JOIN (SELECT cntr_value,OBJECT_NAME
FROM sys.dm_os_performance_counters
WHERE counter_name=\'Buffer cache hit ratio base\' AND OBJECT_NAME=\'{$MSSQL.INSTANCE}:Buffer Manager\') b
ON a.OBJECT_NAME=b.OBJECT_NAME
WHERE a.counter_name=\'Buffer cache hit ratio\' AND a.OBJECT_NAME=\'{$MSSQL.INSTANCE}:Buffer Manager\'
UNION
SELECT a.object_name,\'WorktablesFromCacheRatio\' as counter_name,\'\' as instance_name,cast(a.cntr_value*100.0/b.cntr_value as dec(3,0)) as cntr_value
FROM sys.dm_os_performance_counters a
JOIN (SELECT cntr_value,OBJECT_NAME
FROM sys.dm_os_performance_counters
WHERE counter_name=\'Worktables From Cache Base\' AND OBJECT_NAME=\'{$MSSQL.INSTANCE}:Access Methods\') b
ON a.OBJECT_NAME=b.OBJECT_NAME
WHERE a.counter_name=\'Worktables From Cache Ratio\' AND a.OBJECT_NAME=\'{$MSSQL.INSTANCE}:Access Methods\'
UNION
SELECT a.object_name,\'CacheHitRatio\' as counter_name,\'_Total\' as instance_name,cast(a.cntr_value*100.0/b.cntr_value as dec(3,0)) as cntr_value
FROM sys.dm_os_performance_counters a
JOIN (SELECT cntr_value,OBJECT_NAME
FROM sys.dm_os_performance_counters
WHERE counter_name=\'Cache Hit Ratio base\' AND OBJECT_NAME=\'{$MSSQL.INSTANCE}:Plan Cache\' AND instance_name=\'_Total\') b
ON a.OBJECT_NAME=b.OBJECT_NAME
WHERE a.counter_name=\'Cache Hit Ratio\' AND a.OBJECT_NAME=\'{$MSSQL.INSTANCE}:Plan Cache\' AND instance_name=\'_Total\'
';

//$rs= odbc_exec($db, $get_last_backup);
$rs= odbc_exec($db, $SQL_Exec_String);

$arr    = array();
$x      = 1;

while (odbc_fetch_row($rs)) {
  for ($y = 1; $y <= odbc_num_fields($rs); $y++) 
    $arr[$x][$y] = odbc_result($rs,$y);
  $x++;
}

if ($x > 1) print_r ($arr);

?>