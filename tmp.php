<?php
echo xdebug_time_index()."\n";
for($i=0; $i<3; $i++){
        echo xdebug_time_index()."<br />\n";
        sleep(1);
}
?>