<?php
require_once 'util.php';
$client = get_client();
$total_size = 0;
?>
<html>
<head>
<title>Everbox for Wordpress</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<link rel="stylesheet" type="text/css" href="css/style.css">
</head>
<body>
  <div class="wrapper">
    <div class="header">
      <img src="img/everbox.png" border=0/>
      <h2><strong>Everbox</strong> for wordpress</h2>
    </div>
    <p> <a href="<?php echo rawurldecode($_GET['back']); ?>">Back to Wordpress </a></p>
    <div class="main">
      <?php
      $dir = $client->dir('/home/'.$_GET['folder']);
      foreach ($dir['entries'] as $entry):
      ?>
      <div class="item">
        <div class="select"> <input name="items" type="checkbox" value="1"></div>
        <div class="type"> <img class="icon" height="16" src="img/archive.png" width ="16"/></div>
        <div class="title"> <a href="#"><?php echo htmlspecialchars(basename($entry['path'])) ?></a></div>
        <div class="size"> <?php echo size_readable($entry['fileSize']);$total_size += $entry['fileSize']; ?> </div>
        <div class="clear"></div>
      </div>
      <?php endforeach;?> 
      <input id="commit" name="commit" type="submit" value="Delete Selected"/>
      <p>Total: <strong><?php echo size_readable($total_size) ?></strong><br/>Everbox usage: <strong>35M</strong> / 7000M</p>
      <div class="clear"></div>
    </div>
    <div class="footer">
      <ul>
        <li> <a href="http://www.everbox.com">Everbox </a></li>
        <li> <a href="http://www.everbox.com">Help </a></li>
      </ul>
      <p> &copy;2011 kisbear.com</p>
      <div style="clear:both"></div>
    </div>
  </div>
</body>
</html>
