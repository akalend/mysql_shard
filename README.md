# mysql_shard - php package for MySQL scaling.

The client wrapper on mysqlnd driver for scaling database

##Basic

**The Sharding** - is the method of dividing  of the large logical table to many small phisycal tables. The type of CONFEDERATED Mysql Tables is simle sharding.

The **Strategy of Sharding** is algorithm of distribution database records by phisycal tables.

The **Sharding criterion** is function or rule by according to which the record send to one or other database tables


#Requitments

- MySQL 5.0 or more
- Redis 2.0 or more

If You use other Kyy/Value NoSQL (Not Redis), You can use the it. For example, MemcacheDb, Tarantool or AeroSpike. Send me message an I add the new PHP-class for your storage engine. 


##Use Sharding Strategy

- Linear
- Cycle
- Monthly
- Geo (in developing)


##Linear Sharding

The sharding use increasing id and data filling one table (table_0), then another table (table_1) and etc (table_2, table_3 ...).


Insert Example:

```php
	<?php
	// get some data
	$data = get_some_data();

	// load config file
	$conf = Config::get('sharding');

	// MysqlShard constructor
    $sharding = new MysqlShard($conf);

	// create sharding strategy
    $strategy = new LinearStrategy(null,'lines', $sharding->getConfig());
    
    //SQL template
    $sql = "INSERT INTO %db.logdata_%t (data) VALUES('$data')";

    //execute query
    $sharding->query($sql);
```

Select Example:

```php
	<?php
		
	// load config file
	$conf = Config::get('sharding');

	// MysqlShard constructor
    $sharding = new MysqlShard($conf);

	// create sharding strategy $data_id is some id your data
    $strategy = new LinearStrategy($data_id,'lines', $sharding->getConfig());
        
    //query template
    $sql = "SELECT * FROM %db.logdata_%t WHERE id=$data_id";

    //executing
    $res = $sharding->query($sql);

    // get data from dataset
    $row = $res->fetch_assoc();
    var_dump($row);
```

##SQL Template 

So fprint PHP function, the query template has psevdo symbols:
- %db the database name with numbers, database_1, database_2 end etc, for example.
- %t the table name with numbers, tablename_1, tablename_2 end etc, for example.

The name and numbers of database and table calculate by across to a predetermined strategy.


##Cycle Sharding Example

```php
	<?php 
		
	// load config file
	$conf = Config::get('sharding');

	// MysqlShard constructor
    $sharding = new MysqlShard($conf);

	$date = date('Y-m'); // Set current month

	// create sharding strategy $user_id is some id user profile
    $strategy = new MonthStrategy($user_id,'months', $sharding->getConfig(), $date);
        
    $sql = "INSERT INTO %db.stats_%t (data) VALUES('$data')";

    //query executing
   $res = $sharding->query($sql);
```
For data 20 june 2016 the Strategy created tablename: stats_2016_06. If user_id=100, the databane number is user_id % shardCount, 100 % 4 = 0; For UserId eq 100 this data was inserted to months_0.stats_2016_06 mysql table.


##Cycle shard reading Example

```php 
<?php 		
	// MysqlShard constructor
    $sharding = new MysqlShard(Config::get('sharding'));

	// create sharding strategy 
    $strategy = new MonthStrategy(null,'months', $sharding->getConfig(),  date('Y-m'));

   // some query for all shards     
    $sql = "SELECT FROM %db.stats_%t (data) WHERE type_id=$x";

    $rows = [];
    foreach ($sharding as $shrdItem) {
    	//query executing
    	$res = $sharItem->query($sql);
    	while( $row = $res->fetch_assoc()) {
    		$rows[] = $row;
    	}
    }

```
This example show how read data from all databases (shards). It use only for Cycle and Month strategy.




##Russian documentation

The more instruction on Russian see [README.rus.md](https://github.com/akalend/mysql_shard/blob/master/README.rus.md)
