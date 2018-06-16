## Description
Dyno was not created for the purpose of replacing Mysql for applications, it was created to facilitate applications sent data quickly without having used asynchronous for MySQL
## How it work ?
__Dyno uses a Packet system__; <br/>it sends and receives Packets by Sockets to be able to sort requests as Base/Table (InputPacket/OutputPacket)
## Can I contribute?
Yes you can! Contributions are welcomed!

## Where can I get the latest .phar?

* Latest phar version here: [Latest .phar](https://github.com/MineBuilderFR/Dyno/releases)

## Installation

Download latest .phar , PHP Binary , and start.sh/start.cmd/start.ps1

### Video 
> No installation video for the moment

## Applications

* __DynoPM__ (PocketMine) (PHP) : [Implementing Dyno for PocketMine-MP](https://github.com/MineBuilderFR/DynoPM)
* __DynoNuke__ (Nukkit) (Java): Soon

## Properties
### Server
> Configuration in server.properties <br/>
* __dyno-port__= Port dyno
* __password__= Password for applications to connect to Dyno
* __lang__= Language
* __async-workers__= Async Workers
* __profile-report-trigger__= 20
* __strict-mode__= Ex: If a float is sent to Dyno while it is an int, dyno will return an exception
* __log-packet-received__= Log the Packets received by Dyno

### MySQL
> __You don't need to have Mysql to use Dyno!__ <br/>
> Mysql is only used for DynToSQL <br/>
> DynToSQL Send Dyno Bases/Tables to Mysql <br/>
> Configuration in mysql.properties <br/>
* __mysql-enabled__= Enabled MySQL
* __auto-send-dyn-to-sql__= Auto send DynToSQL
* __time-auto-send-in-second__= Time in seconds to DynToSQL
* __log-auto-send-sql__= Log 
* __connection-host__= SQL Host
* __connection-port__= SQL Port
* __connection-username__= SQL Username
* __connection-password__= SQL Password
* __connection-database__= SQL Database
* __connection-socket__= SQL Socket

## Licensing information 

`This program is free: you can redistribute it and/or modify
it under the terms of the GNU Lesser General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.`

## Current version
* __PHP__: > 7.2 
* __pthread__ : > 3.1.5

__Dyno__: 

* Version: 1.0
* API version: 1.0.0
* CodeName: SugarLaunch

## Extensions needed
* __curl__ , __yaml__, __zlib__ , __pthreads__, __sockets__