<?php

/*
 * Автор: Spark108 | Rubukkit: Sparksys
 * Mail: spark.vat@gmail.com
 * Назначение файла: Генерация и вывод мониторинга.
*/

define('PATH_ROOT', dirname(__FILE__).'/');

class Exc extends \Exception{}

class Monitoring{
	private static $lmon;
	
	private static $config;
	public static $servers;
	private static $template;
	private static $template_cache;
	
	private static $CurPlayers = 0;
	private static $MaxPlayers = 0;
	private static $Percent_Of_Online;
	
	public static function getMon(){
		if(self::$lmon === null) {
			self::$lmon = new self;
		}
		return self::$lmon;
	}
	
	//=========================================================================================//
	
	function __construct(){
        self::$template['main'] = file_get_contents(PATH_ROOT.'template/main.tpl');
        self::$template['progress'] = file_get_contents(PATH_ROOT.'template/progress.tpl');
        
        self::$config = include_once(PATH_ROOT.'config.php');
		self::$servers = include_once(PATH_ROOT.'servers.php');
		
		self::Info();
    }
	//=========================================================================================//
	
	public static function Rend(){
		if(self::cache('next_update') > time() && self::$config['cache_monitoring'] == true) {
			echo self::cache('monitoring');
		} else {
			
			self::$servers = array_merge(self::$servers, array(
				'Онлайн :' => array(
					'CurPlayers' => self::$CurPlayers,
					'MaxPlayers' => self::$MaxPlayers,
					'Percent_Of_Online' => self::$Percent_Of_Online,
				)
			));
			
            foreach(self::$servers as $key=>$value) {
                $matches = array(
                    '{title}' => $key,
                    '{CurPlayers}' => $value['CurPlayers'],
                    '{MaxPlayers}' => $value['MaxPlayers'],
                    '{Percent_Of_Online}' => 'width:'.$value['Percent_Of_Online']
                );
                self::$template_cache .= str_replace(array_keys($matches), $matches, self::$template['progress']);
            }

            $main = array(
                '{home_dir}' => self::$config['dir'],
                '{server_list}' => self::$template_cache,
				'{TEMPLATE}' => self::$config['href'] . '/template/',
                '{absolute_record}' => self::record(),
				'{record_day}' => self::record_day()
            );
			
            $rend_page = str_replace(array_keys($main), $main, self::$template['main']);

			
            if(self::$config['cache_monitoring'] == true) {
				self::cache('monitoring', $rend_page);
				self::cache('next_update', time()+self::$config['cache_update']);
			}
            
            echo $rend_page;
		}
	}
	
	//=========================================================================================//
	
	private static function Info(){
        foreach(self::$servers as $key=>$server) {
			unset(self::$servers[$key]);
            $address = explode(":", $server['address']);
            
            $Info = self::getServer($address[0], $address[1]);
            
            if($Info !== false) {
                self::$servers[$server['name']] = array(
                    'CurPlayers' => $Info['CurPlayers'],
                    'MaxPlayers' => $Info['MaxPlayers'],
                    'Percent_Of_Online' => $Info['CurPlayers']/$Info['MaxPlayers']*100 . '%',
                );
                self::$CurPlayers += $Info['CurPlayers'];
                self::$MaxPlayers += $Info['MaxPlayers'];
            } else {
                if(self::$config['display_offline'] == true) {
                    self::$servers[$server['name']] = array(
                    'CurPlayers' => self::$config['symbol_offline'],
                    'MaxPlayers' => self::$config['symbol_offline'],
                    'Percent_Of_Online' => '100%',
                   );
                } else {
					unset(self::$servers[$key]);
				}
            }
        }
		
        if(self::$MaxPlayers == 0) {
            self::$CurPlayers = self::$config['symbol_offline'];
            self::$MaxPlayers = self::$config['symbol_offline'];
            self::$Percent_Of_Online = '100%';
        } else {
            self::$Percent_Of_Online = self::$CurPlayers/self::$MaxPlayers*100 . '%';
        }
    }
	
    private static function getServer($ip, $port, $Timeout = 3){
		if(!is_int( $Timeout ) || $Timeout < 0) { throw new \InvalidArgumentException( 'Timeout must be an integer.' ); }

        $socket=@fsockopen($ip, $port, $ErrNo, $ErrStr, $Timeout);
		
		//if($ErrNo || $socket === false) { throw new Exc( 'Could not create socket: ' . $ErrStr ); }
		//Stream_Set_Timeout($socket, $Timeout);
		//Stream_Set_Blocking($socket, true);

        if($socket!==false){
            @fwrite($socket,"\xFE");
            $data=@fread($socket,256);@fclose($socket);
            if($data==false or substr($data,0,1)!="\xFF") return FALSE;
            {
                $info=substr($data,3);$info=iconv('UTF-16BE','UTF-8',$info);
                if($info[1]==="\xA7"&&$info[2]==="\x31"){
                    $info=explode("\x00",$info);
                    return array(
                        'CurPlayers' => intval($info[4]),
                        'MaxPlayers' => intval($info[5]),
                    );
                } else {
                    $info = explode("\xA7",$info);
                    return array(
                        'CurPlayers' => intval($info[1]),
                        'MaxPlayers' => intval($info[2]),
                    );
                }
            }
        } else {
            return FALSE;
        }
    }
	
	//=========================================================================================//
	
	private static function record(){
		$record = self::cache('record');
		if($record !== false) {		
			if($record < self::$CurPlayers) {
				self::cache('record', self::$CurPlayers);
				return self::$CurPlayers;
			}
			return $record;
		} else {
			self::cache('record', self::$CurPlayers);
			return self::$CurPlayers;
		}
    }
	
	private static function record_day(){
		$record = self::cache('record_day');
		if($record !== false) {
			if($record['time_update'] > time()) {	
				if($record['record'] < self::$CurPlayers) {
					$record['record'] = self::$CurPlayers;
					self::cache('record_day', $record);
				}
				return $record['record'];
			} else {
				$old_record = array(
					array(
						'time' => mktime(0,0,0,date( "m", $record['time_update']),date( "d", $record['time_update']),date("y", $record['time_update']))-(24*60*60),
						'record' => $record['record']
					),
				);
				
				if($old_record['record'] == self::$config['symbol_offline']) {
					$old_record['record'] = 0;
				}
				$old = self::cache('old_record');
				if($old !== false && is_array($old)) {
					$old_record = array_merge($old, $old_record);
				}
				self::cache('old_record', $old_record);
				
				$record['time_update'] = mktime(0,0,0,date( "m",time()),date( "d",time()),date("y",time()))+(24*60*60);
				$record['record'] = self::$CurPlayers;
				self::cache('record_day', $record);
			}
		} else {
			$record = array(
				'time_update' => mktime(0,0,0,date( "m",time()),date( "d",time()),date("y",time()))+(24*60*60),
				'record' => 0
			);
			self::cache('record_day', $record);
			return $record['record'];
		}
    }
	
	//=========================================================================================//
	
    private static function cache($name, $value = null){
		$cache_dir = PATH_ROOT.'temp/cache/';
		if(!file_exists($cache_dir)) mkdir($cache_dir, 0755, true);
		
		if($value != null) {
			$file_cache = fopen($cache_dir.md5($name).'.temp', 'w');
			if(is_array($value)) {
				$json = json_encode($value);	
				$cache_write = fwrite($file_cache, $json);
			} else {
				$cache_write = fwrite($file_cache, $value);
			}
		} else {
			$file_cache = @file_get_contents(PATH_ROOT.'temp/cache/'.md5($name).'.temp');
			
			if(is_array(json_decode($file_cache, true))) {
				return json_decode($file_cache, true);
			} else {
				return $file_cache;
			}
		}
		fclose($file_cache);
		return $cache_write;
    }
	
	//=========================================================================================//
}

Monitoring::getMon();
Monitoring::Rend(); 
