<form enctype="multipart/form-data" action="find.php" method="POST">
    pass: <input name="pass" type="text" value="<?php echo $pass;?>"/><br>
    BSSID: <input name="bssid" type="text" value="<?php echo $bssid;?>" /><br>
    ESSID: <input name="essid" type="text" value="<?php echo $essid;?>" /><br>
	
    <input type="submit" value="Find" />
</form>