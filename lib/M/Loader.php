<?php

class M_Loader
{	
	/**
	 * __autoload: autoload function for overwirte php std __autoload function 
	 * 
	 * @param string $className class or interface name, 
	 *               undercore in classname will be replaced by directory seperator 
	 */	
	public static function __autoload($className) 
	{				
		if(class_exists($className, false)
		|| interface_exists($className, false))
			return ;	
			
		include str_replace('_', DIRECTORY_SEPARATOR, $className).'.php';	
		
		if(!class_exists($className, false)
		&& !interface_exists($className, false))
			trigger_error("class/interface not found ".$className, E_USER_ERROR);
	}
		
	/**
	 * startAutoLoader: sets a autoloader function for classes, only for version >= php5
	 * 
	 * @param array $includeLocations optional, additional include folder 
	 */	
	public static function startAutoLoader($includeLocations = null)
	{
		self::addIncludePath($includeLocations);
			
		function __autoload($c) 
		{
		    M_Loader::__autoload($c);
		}
	}
	
	/**
	 * addIncludePath: add a include path for class loading
	 * 
	 * @param array $aData include pathes
	 */
	public static function addIncludePath($includeLocations)
	{		
		if(empty($includeLocations))
			return ;
			
		if(!is_array($includeLocations))
			$aData = array($includeLocations);			
		
		set_include_path(implode(PATH_SEPARATOR, $includeLocations) . PATH_SEPARATOR . get_include_path());    
	}
	
	public static function getIncludePathes()
	{	
		return explode(PATH_SEPARATOR, get_include_path());
	}
	
	/**
	 * addIncludeFile: add a include file 
	 * 
	 * @param string $file path to file
	 */
	public static function includeFile($file)
	{		
		if(!file_exists($file))
			return false;
			
		include_once $file;
		
		return true;
	}
}
