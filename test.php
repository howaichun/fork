<?php

$pid = pcntl_fork();
if($pid==-1){
	die('fork failed');
}else if($pid == 0){

}else{
}
